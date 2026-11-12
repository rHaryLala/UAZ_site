<?php

namespace App\Support;

/**
 * Présentation et débouchés des mentions, tels que publiés sur le site zurcher.edu.mg (fiches « Formations », en français, anglais et
 * malagasy) : accroche, parcours de Licence, Master associé quand il existe, débouchés. Rien n'est ajouté à ce que le site actuel affiche.
 * Partagé par le seeder (nouvelles installations) et par la migration de rattrapage (sites déjà installés).
 *
 * Les textes malagasy sont repris du site actuel ; ils restent à faire relire par un locuteur natif (voir docs/TODO-CONTENU.md).
 */
final class FormationContent
{
    /** Intitulés des rubriques et durées, par langue. */
    private const LABELS = [
        'fr' => ['tracks' => 'Parcours de Licence', 'master' => 'Master', 'licence' => '3 ans · 180 crédits ECTS · en présentiel à Sambaina', 'master_facts' => '2 ans · 120 crédits ECTS'],
        'en' => ['tracks' => 'Bachelor\'s tracks', 'master' => 'Master\'s degree', 'licence' => '3 years · 180 ECTS credits · on campus in Sambaina', 'master_facts' => '2 years · 120 ECTS credits'],
        'mg' => ['tracks' => 'Lalam-pianarana Licence', 'master' => 'Master', 'licence' => '3 taona · crédits ECTS 180 · eny an-toerana any Sambaina', 'master_facts' => '2 taona · crédits ECTS 120'],
    ];

    /** Mention (colonne « mention » de la formation, en français) => contenu par langue. */
    private const DATA = [
        'Théologie' => [
            'fr' => ['tagline' => 'Approfondir les Écritures et se préparer au ministère et au service.', 'tracks' => ['Théologie pastorale', 'Théologie biblique'], 'master' => null, 'careers' => ['Pasteur·e', 'Aumônier·ère', 'Enseignement religieux', 'Ministères de jeunesse']],
            'en' => ['tagline' => 'Studying the Scriptures in depth and preparing for ministry and service.', 'tracks' => ['Pastoral theology', 'Biblical theology'], 'master' => null, 'careers' => ['Pastor', 'Chaplain', 'Religious education', 'Youth ministries']],
            'mg' => ['tagline' => 'Mandalina ny Soratra Masina sy miomana amin\'ny asa fanompoana.', 'tracks' => ['Teolojia pastoraly', 'Teolojia ara-Baiboly'], 'master' => null, 'careers' => ['Mpitandrina', 'Mpitondra fivavahana (aumônier)', 'Fampianarana ara-pinoana', 'Asa fanompoana ho an\'ny tanora']],
        ],
        'Communication' => [
            'fr' => ['tagline' => 'Informer, raconter et convaincre, des médias à l\'entreprise.', 'tracks' => ['Communication d\'entreprise', 'Média et journalisme'], 'master' => ['Communication (nouveau en 2026)'], 'careers' => ['Journaliste', 'Chargé·e de communication', 'Community manager', 'Attaché·e de presse']],
            'en' => ['tagline' => 'Informing, storytelling and persuading, from the media to the corporate world.', 'tracks' => ['Corporate communication', 'Media and journalism'], 'master' => ['Communication (new in 2026)'], 'careers' => ['Journalist', 'Communications officer', 'Community manager', 'Press officer']],
            'mg' => ['tagline' => 'Mampahafantatra, mitantara ary mandresy lahatra, manomboka amin\'ny haino aman-jery ka hatrany amin\'ny orinasa.', 'tracks' => ['Serasera ho an\'ny orinasa', 'Haino aman-jery sy asa fanaovan-gazety'], 'master' => ['Serasera (vaovao amin\'ny 2026)'], 'careers' => ['Mpanao gazety', 'Tompon\'andraikitra amin\'ny serasera', 'Community manager', 'Mpitondra tenin\'ny orinasa']],
        ],
        'Études anglophones' => [
            'fr' => ['tagline' => 'Maîtriser l\'anglais pour traduire, enseigner et ouvrir des portes à l\'international.', 'tracks' => ['Traduction et interprétation', 'Sociolinguistique'], 'master' => ['Langues étrangères appliquées', 'Linguistique appliquée'], 'careers' => ['Traducteur·rice / interprète', 'Enseignant·e d\'anglais', 'Relations internationales', 'Tourisme']],
            'en' => ['tagline' => 'Mastering English to translate, teach and open doors internationally.', 'tracks' => ['Translation and interpreting', 'Sociolinguistics'], 'master' => ['Applied foreign languages', 'Applied linguistics'], 'careers' => ['Translator / interpreter', 'English teacher', 'International relations', 'Tourism']],
            'mg' => ['tagline' => 'Mifehy ny teny anglisy mba handika teny, hampianatra ary hanokatra varavarana any ivelany.', 'tracks' => ['Fandikan-teny an-tsoratra sy am-bava', 'Sosiolinguistika'], 'master' => ['Fiteny vahiny ampiharina', 'Linguistika ampiharina'], 'careers' => ['Mpandika teny', 'Mpampianatra teny anglisy', 'Fifandraisana iraisam-pirenena', 'Fizahantany']],
        ],
        'Gestion' => [
            'fr' => ['tagline' => 'Piloter, financer et faire grandir les entreprises avec intégrité.', 'tracks' => ['Management des affaires', 'Finance et comptabilité'], 'master' => ['Management des affaires', 'Finance et comptabilité'], 'careers' => ['Comptable', 'Contrôleur·se de gestion', 'Responsable administratif', 'Entrepreneur·e']],
            'en' => ['tagline' => 'Leading, financing and growing businesses with integrity.', 'tracks' => ['Business management', 'Finance and accounting'], 'master' => ['Business management', 'Finance and accounting'], 'careers' => ['Accountant', 'Management controller', 'Administrative manager', 'Entrepreneur']],
            'mg' => ['tagline' => 'Mitarika, mamatsy vola ary mampandroso orinasa amim-pahamarinana.', 'tracks' => ['Fitantanana orinasa', 'Vola sy kaonty'], 'master' => ['Fitantanana orinasa', 'Vola sy kaonty'], 'careers' => ['Mpitana kaonty', 'Mpanara-maso ny fitantanana', 'Tompon\'andraikitra amin\'ny fitantanana', 'Mpandraharaha']],
        ],
        'Droit' => [
            'fr' => ['tagline' => 'Comprendre et appliquer la règle de droit, au public comme au privé.', 'tracks' => ['Droit public', 'Droit privé'], 'master' => null, 'careers' => ['Juriste d\'entreprise', 'Administration publique', 'Concours de la magistrature', 'Notariat']],
            'en' => ['tagline' => 'Understanding and applying the rule of law, in the public and private sectors.', 'tracks' => ['Public law', 'Private law'], 'master' => null, 'careers' => ['Corporate lawyer', 'Public administration', 'Judiciary entrance exams', 'Notarial practice']],
            'mg' => ['tagline' => 'Mahatakatra sy mampihatra ny lalàna, na amin\'ny sehatra ho an\'ny daholobe na amin\'ny tsy miankina.', 'tracks' => ['Lalàna ho an\'ny daholobe', 'Lalàna tsy miankina'], 'master' => null, 'careers' => ['Mpahay lalàna ao amin\'ny orinasa', 'Fitantanan-draharaham-panjakana', 'Fifaninanana ho mpitsara', 'Notera']],
        ],
        'Sciences infirmières' => [
            'fr' => ['tagline' => 'Former des soignants compétents, humains et engagés au service de la santé.', 'tracks' => ['Infirmerie générale', 'Maïeutique (sage-femme)'], 'master' => null, 'careers' => ['Infirmier·ère en milieu hospitalier', 'Sage-femme', 'Santé communautaire', 'Centres de santé et ONG']],
            'en' => ['tagline' => 'Training skilled, caring and committed health professionals.', 'tracks' => ['General nursing', 'Midwifery'], 'master' => null, 'careers' => ['Hospital nurse', 'Midwife', 'Community health', 'Health centres and NGOs']],
            'mg' => ['tagline' => 'Mamolavola mpitsabo mahay, manaja ny olona ary vonona hanompo ho an\'ny fahasalamana.', 'tracks' => ['Mpitsabo mpanampy ankapobeny', 'Mpampivelona (sage-femme)'], 'master' => null, 'careers' => ['Mpitsabo mpanampy any amin\'ny hopitaly', 'Mpampivelona', 'Fahasalamam-piarahamonina', 'Toeram-pitsaboana sy ONG']],
        ],
        'Informatique' => [
            'fr' => ['tagline' => 'Concevoir des logiciels et administrer les systèmes qui font tourner les organisations.', 'tracks' => ['Système d\'information et génie logiciel', 'Maintenance et administration de réseau'], 'master' => ['Système d\'information et génie logiciel', 'Maintenance et administration de réseau'], 'careers' => ['Développeur·se', 'Administrateur·rice réseau', 'Chef de projet informatique', 'Analyste SI']],
            'en' => ['tagline' => 'Designing software and running the systems that power organisations.', 'tracks' => ['Information systems and software engineering', 'Network maintenance and administration'], 'master' => ['Information systems and software engineering', 'Network maintenance and administration'], 'careers' => ['Software developer', 'Network administrator', 'IT project manager', 'Systems analyst']],
            'mg' => ['tagline' => 'Mamorona rindrankajy sy mitantana ireo rafitra mampandeha ny fikambanana.', 'tracks' => ['Rafitra fampahalalana sy famoronana rindrankajy', 'Fikojakojana sy fitantanana tambajotra'], 'master' => ['Rafitra fampahalalana sy famoronana rindrankajy', 'Fikojakojana sy fitantanana tambajotra'], 'careers' => ['Mpamorona rindrankajy', 'Mpitantana tambajotra', 'Mpitarika tetikasa informatika', 'Mpandinika rafitra fampahalalana']],
        ],
    ];

    /** Une mention est-elle décrite ici ? */
    public static function has(string $mention): bool
    {
        return isset(self::DATA[$mention]);
    }

    /** Rubrique « Présentation » : accroche, parcours de Licence et, s'il existe, Master. Null si la mention est inconnue. */
    public static function description(string $mention, string $locale = 'fr'): ?string
    {
        $data = self::DATA[$mention][$locale] ?? null;

        if (! $data) {
            return null;
        }

        $label = self::LABELS[$locale];
        $html = '<p>'.e($data['tagline']).'</p>'
            .'<h3>'.e($label['tracks']).'</h3><p>'.e($label['licence']).'</p>'.self::list($data['tracks']);

        if ($data['master']) {
            $html .= '<h3>'.e($label['master']).'</h3><p>'.e($label['master_facts']).'</p>'.self::list($data['master']);
        }

        return $html;
    }

    /** Rubrique « Débouchés » : une liste. Null si la mention est inconnue. */
    public static function careers(string $mention, string $locale = 'fr'): ?string
    {
        $data = self::DATA[$mention][$locale] ?? null;

        return $data ? self::list($data['careers']) : null;
    }

    /** @param list<string> $items */
    private static function list(array $items): string
    {
        return '<ul>'.collect($items)->map(fn ($item) => '<li>'.e($item).'</li>')->implode('').'</ul>';
    }
}
