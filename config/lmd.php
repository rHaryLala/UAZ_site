<?php

/*
 * Données de la page « Système LMD » (resources/views/pages/lmd.blade.php).
 * Source : texte de la page « Système LMD » du site actuel de l'UAZ (importé dans la page, et repris ici, mot pour mot, pour que la mise en page
 * puisse en tirer les chiffres : années, semestres, crédits ECTS). Rien n'est inventé : le Doctorat n'est mentionné que comme « poursuite » après
 * le Master, aucune durée ni crédit ne lui est attribué. À relire avec l'UAZ si le texte de la page change : voir docs/TODO-CONTENU.md.
 */
return [
    'levels' => [
        'licence' => [
            'title' => 'Licence',
            'kicker' => 'Premier grade universitaire',
            'years' => 3,
            'semesters' => 6,
            'ects' => 180,
            'text' => 'La Licence est le premier grade universitaire, obtenu après trois années d\'études (six semestres) et la validation de 180 crédits ECTS.',
        ],
        'master' => [
            'title' => 'Master',
            'kicker' => 'Spécialisation approfondie',
            'years' => 2,
            'stages' => ['M1', 'M2'],
            'ects' => 120,
            'total' => 300,
            'text' => 'Le Master est accessible après l\'obtention de la Licence et requiert deux années d\'études supplémentaires (M1 et M2), soit la validation de 120 crédits ECTS (pour un total de 300 crédits ECTS depuis le Bac). Il permet une spécialisation approfondie.',
        ],
        'doctorat' => [
            'title' => 'Doctorat',
            'kicker' => 'La suite',
            'text' => 'Les diplômes de Master préparent les étudiants à des postes de responsabilité ou à la poursuite vers un cycle de Doctorat.',
        ],
    ],

    'intro' => 'L\'Université Adventiste Zurcher adopte le système LMD pour ses programmes d\'étude.',

    'licence_text' => [
        'L\'UAZ propose plusieurs mentions académiques reconnues, telles que Informatique, Théologie, Sciences infirmières, Droit, Études anglophones, Communication, Gestion.',
        'Chaque mention offre un parcours complet, alliant enseignement théorique et pratique, afin de préparer les étudiants aux défis professionnels et scientifiques.',
    ],

    // formation : intitulé de la mention dans la table des formations (lien vers sa fiche) ; tracks : parcours de la page.
    'licence_mentions' => [
        ['name' => 'Informatique', 'formation' => 'Informatique', 'tracks' => ['Système d\'information et génie logiciel', 'Maintenance et administration de réseau']],
        ['name' => 'Théologie', 'formation' => 'Théologie', 'tracks' => ['Pastorale', 'Biblique']],
        ['name' => 'Sciences infirmières', 'formation' => 'Sciences infirmières', 'tracks' => ['Infirmerie générale', 'Maïeutique (sage-femme)']],
        ['name' => 'Droit', 'formation' => 'Droit', 'tracks' => ['Public', 'Privé']],
        ['name' => 'Études anglophones', 'formation' => 'Études anglophones', 'tracks' => ['Traduction et interprétation', 'Socio-linguistique']],
        ['name' => 'Communication', 'formation' => 'Communication', 'tracks' => ['Entreprise', 'Média et Journalisme']],
        ['name' => 'Gestion', 'formation' => 'Gestion', 'tracks' => ['Management des affaires', 'Finance et comptabilité']],
    ],

    'master_text' => 'Le programme de Master de l\'Université Adventiste Zurcher est conçu pour approfondir les connaissances acquises en Licence (Bac+3) et permettre une spécialisation en deux années supplémentaires (M1 et M2). Les diplômes de Master sont délivrés dans les mentions suivantes : Gestion, Informatique, Études anglophones.',

    'master_mentions' => [
        ['name' => 'Gestion', 'tracks' => ['Administration des affaires', 'Finance et comptabilité']],
        ['name' => 'Informatique', 'tracks' => ['Systèmes d\'information et génie logiciel', 'Administration système et réseaux informatiques']],
        ['name' => 'Études anglophones', 'tracks' => ['Langues étrangères appliquées - LEAP', 'Linguistique appliquée - LIAP']],
    ],
];
