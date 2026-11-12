<?php

use App\Models\NewsletterSubscriber;
use App\Support\MailpoetImport;

/** L'en-tête exact d'un export MailPoet (français), apostrophes typographiques comprises. */
const MAILPOET_HEADER = 'E-mail,Prénom,Nom,"Temps d’abonnement","Temps de confirmation","État de la liste","État global",Liste';

/** Écrit un export de test (adresses fictives) et renvoie son chemin. */
function mailpoetFile(array $rows, string $header = MAILPOET_HEADER, string $delimiter = ',', string $encoding = 'UTF-8', bool $bom = false): string
{
    $content = ($bom ? "\xEF\xBB\xBF" : '').$header."\n".implode("\n", $rows)."\n";
    $content = str_replace(',', $delimiter, $content);

    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, $encoding, 'UTF-8');
    }

    $path = tempnam(sys_get_temp_dir(), 'mailpoet').'.csv';
    file_put_contents($path, $content);

    return $path;
}

/** Une ligne d'export : adresse, état global, date d'abonnement, date de confirmation. */
function mailpoetRow(string $email, string $state = 'subscribed', string $at = '2026-07-02 05:27:33', string $confirmed = ''): string
{
    return sprintf('%s,Prénom,Nom,"%s","%s",%s,%s,"Liste De Diffusion De Newsletter"', $email, $at, $confirmed, $state === 'bounced' ? 'subscribed' : $state, $state);
}

it('turns subscribed and unconfirmed addresses into subscribers, keeps the unsubscribed ones as such, and ignores the bounces', function () {
    $path = mailpoetFile([
        mailpoetRow('actif@exemple.test', 'subscribed', '2026-06-22 12:19:27', '2026-06-23 05:43:37'),
        mailpoetRow('attente@exemple.test', 'unconfirmed', '2026-07-21 14:15:32'),
        mailpoetRow('parti@exemple.test', 'unsubscribed', '2026-06-30 14:05:53'),
        mailpoetRow('rebond@exemple.test', 'bounced'),
    ]);

    $result = MailpoetImport::run($path);

    expect($result['total'])->toBe(4)
        ->and($result['counts'])->toMatchArray(['active_added' => 2, 'unconfirmed_included' => 1, 'unsubscribed_added' => 1, 'bounced' => 1])
        ->and(NewsletterSubscriber::count())->toBe(3)
        ->and(NewsletterSubscriber::where('email', 'rebond@exemple.test')->exists())->toBeFalse();

    expect(NewsletterSubscriber::where('email', 'actif@exemple.test')->first()->status())->toBe('active')
        // « unconfirmed » : abonné directement, l'abonnement est immédiat sur ce site
        ->and(NewsletterSubscriber::where('email', 'attente@exemple.test')->first()->status())->toBe('active')
        ->and(NewsletterSubscriber::where('email', 'parti@exemple.test')->first()->status())->toBe('unsubscribed');

    // Seuls les abonnés actifs reçoivent la newsletter : les deux premiers, pas le désinscrit.
    expect(NewsletterSubscriber::active()->pluck('email')->sort()->values()->all())->toBe(['actif@exemple.test', 'attente@exemple.test']);
});

it('keeps the real subscription and confirmation dates', function () {
    MailpoetImport::run(mailpoetFile([
        mailpoetRow('confirme@exemple.test', 'subscribed', '2026-06-22 12:19:27', '2026-06-23 05:43:37'),
        mailpoetRow('sansconfirmation@exemple.test', 'subscribed', '2026-07-02 05:27:33'),
    ]));

    $confirmed = NewsletterSubscriber::where('email', 'confirme@exemple.test')->first();
    $plain = NewsletterSubscriber::where('email', 'sansconfirmation@exemple.test')->first();

    expect($confirmed->created_at->format('Y-m-d H:i:s'))->toBe('2026-06-22 12:19:27')
        ->and($confirmed->confirmed_at->format('Y-m-d H:i:s'))->toBe('2026-06-23 05:43:37')
        // Pas de date de confirmation (contacts importés en bloc) : la date d'abonnement en tient lieu.
        ->and($plain->confirmed_at->format('Y-m-d H:i:s'))->toBe('2026-07-02 05:27:33');
});

it('cleans the addresses, drops the invalid ones and counts a duplicate only once', function () {
    $result = MailpoetImport::run(mailpoetFile([
        mailpoetRow('Majuscules@Exemple.TEST'),
        mailpoetRow('pointvirgule@exemple.test ;'),
        mailpoetRow('majuscules@exemple.test'), // doublon (casse différente)
        mailpoetRow('pas-une-adresse'),
        mailpoetRow('double@arobase@exemple.test'),
    ]));

    expect($result['counts'])->toMatchArray(['active_added' => 2, 'duplicates' => 1, 'invalid' => 2])
        ->and(NewsletterSubscriber::orderBy('email')->pluck('email')->all())->toBe(['majuscules@exemple.test', 'pointvirgule@exemple.test'])
        ->and($result['invalid'])->toContain('pas-une-adresse');
});

it('never subscribes again someone who unsubscribed on this site, and honours an unsubscription made in MailPoet', function () {
    $left = NewsletterSubscriber::create(['email' => 'parti@exemple.test', 'unsubscribed_at' => now()->subDay()]);
    $waiting = NewsletterSubscriber::create(['email' => 'attente@exemple.test', 'confirmation_sent_at' => now()->subDay()]);
    $active = NewsletterSubscriber::create(['email' => 'actif@exemple.test', 'confirmed_at' => now()->subWeek()]);
    $activeToLeave = NewsletterSubscriber::create(['email' => 'depart@exemple.test', 'confirmed_at' => now()->subWeek()]);

    $result = MailpoetImport::run(mailpoetFile([
        mailpoetRow('parti@exemple.test', 'subscribed'),   // désinscrit ici, abonné dans MailPoet : on ne réinscrit pas
        mailpoetRow('attente@exemple.test', 'subscribed'), // en attente ici, abonné dans MailPoet : confirmé
        mailpoetRow('actif@exemple.test', 'subscribed'),   // déjà actif : inchangé
        mailpoetRow('depart@exemple.test', 'unsubscribed'), // désinscrit dans MailPoet : désinscrit aussi ici
    ]));

    expect($result['counts'])->toMatchArray(['kept_unsubscribed' => 1, 'active_confirmed' => 1, 'already' => 1, 'unsubscribed_marked' => 1, 'active_added' => 0])
        ->and($left->fresh()->status())->toBe('unsubscribed')
        ->and($waiting->fresh()->status())->toBe('active')
        ->and($active->fresh()->confirmed_at->toDateString())->toBe(now()->subWeek()->toDateString())
        ->and($activeToLeave->fresh()->status())->toBe('unsubscribed');
});

it('can be run again without creating duplicates or changing anything', function () {
    $path = mailpoetFile([mailpoetRow('un@exemple.test'), mailpoetRow('deux@exemple.test', 'unconfirmed'), mailpoetRow('trois@exemple.test', 'unsubscribed')]);

    MailpoetImport::run($path);
    $again = MailpoetImport::run($path);

    expect(NewsletterSubscriber::count())->toBe(3)
        ->and($again['counts'])->toMatchArray(['already' => 3, 'active_added' => 0, 'unconfirmed_included' => 0, 'unsubscribed_added' => 0]);
});

it('reports what it would do in a simulation without writing anything', function () {
    NewsletterSubscriber::create(['email' => 'present@exemple.test', 'confirmed_at' => now()]);
    $path = mailpoetFile([mailpoetRow('nouveau@exemple.test'), mailpoetRow('present@exemple.test'), mailpoetRow('attente@exemple.test', 'unconfirmed')]);

    $simulation = MailpoetImport::run($path, dryRun: true);

    expect(NewsletterSubscriber::count())->toBe(1)
        ->and($simulation['counts'])->toMatchArray(['active_added' => 2, 'already' => 1, 'unconfirmed_included' => 1]);

    $real = MailpoetImport::run($path);
    expect($real['counts'])->toBe($simulation['counts'])->and(NewsletterSubscriber::count())->toBe(3);
});

it('reads exports saved with semicolons, a byte order mark or Windows-1252 accents', function () {
    $semicolons = mailpoetFile([mailpoetRow('pv@exemple.test')], delimiter: ';', bom: true);
    $windows = mailpoetFile([mailpoetRow('win@exemple.test')], encoding: 'Windows-1252');

    expect(MailpoetImport::run($semicolons)['counts']['active_added'])->toBe(1)
        ->and(MailpoetImport::run($windows)['counts']['active_added'])->toBe(1)
        ->and(NewsletterSubscriber::orderBy('email')->pluck('email')->all())->toBe(['pv@exemple.test', 'win@exemple.test']);
});

it('reads the columns by their title, whatever their order', function () {
    $path = mailpoetFile(['"Newsletter",subscribed,"ordre@exemple.test"'], '"Liste","État global","E-mail"');

    expect(MailpoetImport::run($path)['counts']['active_added'])->toBe(1)
        ->and(NewsletterSubscriber::where('email', 'ordre@exemple.test')->exists())->toBeTrue();
});

it('refuses a file that is not a MailPoet export', function () {
    expect(fn () => MailpoetImport::run(mailpoetFile(['a,b'], 'Colonne A,Colonne B')))->toThrow(InvalidArgumentException::class, 'E-mail')
        ->and(fn () => MailpoetImport::run('/chemin/inexistant.csv'))->toThrow(InvalidArgumentException::class);

    $empty = tempnam(sys_get_temp_dir(), 'vide');
    expect(fn () => MailpoetImport::run($empty))->toThrow(InvalidArgumentException::class);
    expect(NewsletterSubscriber::count())->toBe(0);
});

it('writes a readable summary, one sentence per line', function () {
    $result = MailpoetImport::run(mailpoetFile([mailpoetRow('a@exemple.test'), mailpoetRow('b@exemple.test', 'unconfirmed'), mailpoetRow('c@exemple.test', 'bounced'), mailpoetRow('nimporte')]));
    $summary = implode("\n", MailpoetImport::summary($result));

    expect($summary)->toContain('4 ligne(s) lue(s)')->toContain('2 abonné(s) actif(s) ajouté(s) (dont 1 qui n\'avaient pas confirmé dans MailPoet)')
        ->toContain('1 adresse(s) en rebond ignorée(s)')->toContain('1 adresse(s) invalide(s)')->toContain('nimporte')
        ->and(implode("\n", MailpoetImport::summary($result, true)))->toContain('SIMULATION');
});

it('is available as a command with a simulation option', function () {
    $path = mailpoetFile([mailpoetRow('cmd@exemple.test')]);

    $this->artisan('newsletter:import-mailpoet', ['file' => $path, '--dry-run' => true])->expectsOutputToContain('SIMULATION')->assertSuccessful();
    expect(NewsletterSubscriber::count())->toBe(0);

    $this->artisan('newsletter:import-mailpoet', ['file' => $path])->expectsOutputToContain('1 abonné(s) actif(s) ajouté(s)')->assertSuccessful();
    expect(NewsletterSubscriber::count())->toBe(1);

    $this->artisan('newsletter:import-mailpoet', ['file' => '/non/trouve.csv'])->assertFailed();
});
