<?php

use App\Enums\FacultyType;
use App\Enums\FormationLevel;
use App\Enums\PublicationStatus;
use App\Enums\TeamCategory;

// Les libellés passent par __() : il faut l'application (et son traducteur).
uses(Tests\TestCase::class);

it('labels every enum case in French', function (string $enum) {
    foreach ($enum::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBeEmpty();
    }
})->with([PublicationStatus::class, FormationLevel::class, TeamCategory::class, FacultyType::class]);

it('keeps stable stored values (they are persisted in the database and in URLs)', function () {
    expect(PublicationStatus::Draft->value)->toBe('draft')
        ->and(PublicationStatus::Published->value)->toBe('published')
        ->and(FormationLevel::Licence->value)->toBe('licence')
        ->and(FormationLevel::Master->value)->toBe('master')
        ->and(TeamCategory::Teaching->value)->toBe('enseignement');
});

it('colors publication statuses for the back-office', function () {
    expect(PublicationStatus::Published->getColor())->toBe('success')
        ->and(PublicationStatus::Draft->getColor())->toBe('gray');
});
