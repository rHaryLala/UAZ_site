<?php

/*
 * Frais académiques 2026-2027 (resources/views/pages/fees.blade.php, calcul : app/Support/FeeCatalog.php).
 * Source : les six tableaux officiels « UAZ - FRAIS 2026-2027 » de la page (médiathèque). Montants en Ariary.
 * À METTRE À JOUR CHAQUE ANNÉE avec les nouveaux tableaux. Les « totals » sont les totaux imprimés sur les tableaux :
 * un test (tests/Feature/Public/FeesPageTest.php) vérifie que la somme des lignes les retrouve, ce qui protège contre une faute de saisie.
 *
 * Licence : chaque ligne porte 8 montants, dans l'ordre des colonnes des tableaux :
 *   [remise à niveau interne, externe, 1re année interne, externe, 2e année interne, externe, 3e année interne, externe] ; null = « - » ou case vide.
 * Groupes (couleurs de la barre de répartition) : general, tuition, housing, labs, extras.
 */

return [
    'year' => '2026-2027',
    'test_fee' => ['amount' => 20000, 'text' => 'Test de niveau, à régler avant l\'inscription (Licence).'],

    // Service Finance : coordonnées du texte de la page.
    'finance' => [
        'phone' => '034 13 859 75',
        'email' => 'chiefaccount@zurcher.edu.mg',
        'hours' => '7 h 30 – 12 h et 13 h 30 – 17 h',
    ],

    // Master : moyens de paiement et plans, tels qu'imprimés sur l'affiche « Frais d'études M1 ».
    'payment' => [
        'methods' => [
            ['label' => 'MVola', 'value' => '038 53 849 49', 'holder' => 'au nom de UAZ-1'],
            ['label' => 'BMOI', 'value' => '00004 00006 070112 001 28 32', 'holder' => null],
        ],
        'plans' => [
            ['label' => '100 % en une fois', 'text' => 'À l\'inscription ou au mois d\'octobre 2026', 'parts' => [['100 %', 'Inscription ou octobre 2026']]],
            ['label' => '50 % + 50 %', 'text' => '50 % en octobre, 50 % avant l\'examen final', 'parts' => [['50 %', 'Octobre'], ['50 %', 'Avant l\'examen final']]],
            ['label' => '25 % par mois', 'text' => 'Selon les dates d\'échéance établies', 'parts' => [['25 %', 'Échéance'], ['25 %', 'Échéance'], ['25 %', 'Échéance'], ['25 %', 'Échéance']]],
        ],
    ],

    'groups' => [
        'general' => 'Frais généraux',
        'tuition' => 'Écolage',
        'housing' => 'Dortoir et cantine',
        'labs' => 'Laboratoires',
        'extras' => 'Autres frais',
    ],

    'licence' => [
        'years' => ['remise' => 'Remise à niveau', 'l1' => '1re année', 'l2' => '2e année', 'l3' => '3e année'],
        'departments' => [
            'sciences-infirmieres' => [
                'label' => 'Sciences infirmières',
                'semesters' => [
                    1 => [
                        'rows' => [
                            ['label' => 'Frais généraux', 'note' => 'Par semestre', 'group' => 'general', 'values' => [213000, 213000, 213000, 213000, 273000, 273000, 273000, 273000]],
                            ['label' => 'Laboratoire informatique', 'note' => 'Par semestre', 'group' => 'labs', 'values' => [35000, 35000, 35000, 35000, null, null, null, null]],
                            ['label' => 'Dortoir', 'note' => 'Pour les internes', 'group' => 'housing', 'values' => [345000, null, 345000, null, 345000, null, 345000, null]],
                            ['label' => 'Cantine', 'note' => '9 000 Ar par jour · 115 jours · pour les internes', 'group' => 'housing', 'values' => [1035000, null, 1035000, null, 1035000, null, 1035000, null]],
                            ['label' => 'Écolage', 'note' => '19 000 Ar par crédit · pour 30 crédits', 'group' => 'tuition', 'values' => [570000, 570000, 570000, 570000, 570000, 570000, 570000, 570000]],
                            ['label' => 'Voyage d\'étude', 'note' => '2e année · la moitié du prix total chaque semestre', 'group' => 'extras', 'values' => [null, null, null, null, 150000, 150000, null, null]],
                            ['label' => 'Alliance Française', 'note' => null, 'group' => 'extras', 'values' => [156000, 156000, 156000, 156000, null, null, null, null]],
                        ],
                        'totals' => [2354000, 974000, 2354000, 974000, 2373000, 993000, 2223000, 843000],
                    ],
                    2 => [
                        'rows' => [
                            ['label' => 'Frais généraux', 'note' => 'Par semestre', 'group' => 'general', 'values' => [228000, 228000, 228000, 228000, 228000, 228000, 228000, 228000]],
                            ['label' => 'Dortoir', 'note' => 'Pour les internes', 'group' => 'housing', 'values' => [393000, null, 393000, null, 393000, null, 393000, null]],
                            ['label' => 'Cantine', 'note' => '9 000 Ar par jour · 131 jours · pour les internes', 'group' => 'housing', 'values' => [1179000, null, 1179000, null, 1179000, null, 1179000, null]],
                            ['label' => 'Écolage', 'note' => '19 000 Ar par crédit · pour 30 crédits', 'group' => 'tuition', 'values' => [570000, 570000, 570000, 570000, 570000, 570000, 570000, 570000]],
                            ['label' => 'Frais de graduation', 'note' => '3e année', 'group' => 'extras', 'values' => [null, null, null, null, null, null, 150000, 150000]],
                            ['label' => 'Voyage d\'étude', 'note' => '2e année · la moitié du prix total chaque semestre', 'group' => 'extras', 'values' => [null, null, null, null, 150000, 150000, null, null]],
                            ['label' => 'Alliance Française', 'note' => null, 'group' => 'extras', 'values' => [156000, 156000, 156000, 156000, null, null, null, null]],
                            ['label' => 'Frais d\'encadrement', 'note' => '3e année', 'group' => 'extras', 'values' => [null, null, null, null, null, null, 150000, 150000]],
                            ['label' => 'Droit de soutenance', 'note' => '3e année', 'group' => 'extras', 'values' => [null, null, null, null, null, null, 90000, 90000]],
                        ],
                        'totals' => [2526000, 954000, 2526000, 954000, 2520000, 948000, 2760000, 1188000],
                    ],
                ],
            ],
            'autres' => [
                'label' => 'Autres départements',
                'semesters' => [
                    1 => [
                        'rows' => [
                            ['label' => 'Frais généraux', 'note' => 'Par semestre', 'group' => 'general', 'values' => [213000, 213000, 213000, 213000, 213000, 213000, 213000, 213000]],
                            ['label' => 'Laboratoire informatique', 'note' => 'Par semestre', 'group' => 'labs', 'values' => [null, null, 35000, 35000, 35000, 35000, 35000, 35000]],
                            ['label' => 'Laboratoire-simulation communication', 'note' => 'Par semestre', 'group' => 'labs', 'values' => [null, null, null, null, 35000, 35000, 35000, 35000]],
                            ['label' => 'Dortoir', 'note' => 'Pour les internes', 'group' => 'housing', 'values' => [345000, null, 345000, null, 345000, null, 345000, null]],
                            ['label' => 'Cantine', 'note' => '9 000 Ar par jour · 115 jours · pour les internes', 'group' => 'housing', 'values' => [1035000, null, 1035000, null, 1035000, null, 1035000, null]],
                            ['label' => 'Écolage', 'note' => '19 000 Ar par crédit · pour 30 crédits', 'group' => 'tuition', 'values' => [570000, 570000, 570000, 570000, 570000, 570000, 570000, 570000]],
                            ['label' => 'Voyage d\'étude', 'note' => '2e année · la moitié du prix total chaque semestre', 'group' => 'extras', 'values' => [null, null, null, null, 150000, 150000, null, null]],
                            ['label' => 'Alliance Française', 'note' => null, 'group' => 'extras', 'values' => [156000, 156000, 156000, 156000, null, null, null, null]],
                        ],
                        'totals' => [2319000, 939000, 2354000, 974000, 2383000, 1003000, 2233000, 853000],
                    ],
                    2 => [
                        'rows' => [
                            ['label' => 'Frais généraux', 'note' => 'Par semestre', 'group' => 'general', 'values' => [168000, 168000, 168000, 168000, 168000, 168000, 168000, 168000]],
                            ['label' => 'Laboratoire informatique', 'note' => 'Par semestre', 'group' => 'labs', 'values' => [null, null, 35000, 35000, 35000, 35000, 35000, 35000]],
                            ['label' => 'Laboratoire-simulation communication', 'note' => 'Par semestre', 'group' => 'labs', 'values' => [null, null, null, null, 35000, 35000, 35000, 35000]],
                            ['label' => 'Dortoir', 'note' => 'Pour les internes', 'group' => 'housing', 'values' => [393000, null, 393000, null, 393000, null, 393000, null]],
                            ['label' => 'Cantine', 'note' => '9 000 Ar par jour · 131 jours · pour les internes', 'group' => 'housing', 'values' => [1134000, null, 1134000, null, 1134000, null, 1134000, null]],
                            ['label' => 'Écolage', 'note' => '19 000 Ar par crédit · pour 30 crédits', 'group' => 'tuition', 'values' => [570000, 570000, 570000, 570000, 570000, 570000, 570000, 570000]],
                            ['label' => 'Frais de graduation', 'note' => '3e année', 'group' => 'extras', 'values' => [null, null, null, null, null, null, 150000, 150000]],
                            ['label' => 'Voyage d\'étude', 'note' => '2e année · la moitié du prix total chaque semestre', 'group' => 'extras', 'values' => [null, null, null, null, 150000, 150000, null, null]],
                            ['label' => 'Alliance Française', 'note' => null, 'group' => 'extras', 'values' => [156000, 156000, 156000, 156000, null, null, null, null]],
                            ['label' => 'Frais d\'encadrement', 'note' => '3e année', 'group' => 'extras', 'values' => [null, null, null, null, null, null, 150000, 150000]],
                            ['label' => 'Droit de soutenance', 'note' => '3e année', 'group' => 'extras', 'values' => [null, null, null, null, null, null, 90000, 90000]],
                        ],
                        'totals' => [2421000, 894000, 2456000, 929000, 2485000, 958000, 2725000, 1198000],
                    ],
                ],
            ],
        ],
    ],

    // Master : un seul montant par ligne (pas de distinction interne / externe sur les tableaux).
    'master' => [
        'tracks' => [
            'm1' => [
                'label' => 'Master 1 (M1)',
                'hint' => 'Premier et second semestres',
                'semesters' => [
                    1 => [
                        'rows' => [
                            ['label' => 'Frais d\'étude du dossier', 'note' => 'Lors de l\'envoi du dossier', 'group' => 'extras', 'amount' => 17000],
                            ['label' => 'Frais généraux', 'note' => 'Le jour de l\'inscription', 'group' => 'general', 'amount' => 190000],
                            ['label' => 'Écolage', 'note' => '25 000 Ar par crédit · pour 30 crédits · selon le plan de paiement choisi', 'group' => 'tuition', 'amount' => 750000],
                        ],
                        'total' => 957000,
                    ],
                    2 => [
                        'rows' => [
                            ['label' => 'Frais d\'étude du dossier', 'note' => 'Lors de l\'envoi du dossier', 'group' => 'extras', 'amount' => 0],
                            ['label' => 'Frais généraux', 'note' => 'Le jour de l\'inscription', 'group' => 'general', 'amount' => 150000],
                            ['label' => 'Écolage', 'note' => '25 000 Ar par crédit · pour 30 crédits · selon le plan de paiement choisi', 'group' => 'tuition', 'amount' => 750000],
                        ],
                        'total' => 900000,
                    ],
                ],
            ],
            'm2' => [
                'label' => 'Master 2 (M2)',
                'hint' => 'Étudiants sortants · second semestre',
                'semesters' => [
                    2 => [
                        'rows' => [
                            ['label' => 'Frais généraux', 'note' => null, 'group' => 'general', 'amount' => 150000],
                            ['label' => 'Écolage', 'note' => '25 000 Ar par crédit · pour 30 crédits', 'group' => 'tuition', 'amount' => 750000],
                            ['label' => 'Frais de graduation', 'note' => null, 'group' => 'extras', 'amount' => 250000],
                            ['label' => 'Frais d\'encadreur de mémoire Master', 'note' => null, 'group' => 'extras', 'amount' => 250000],
                            ['label' => 'Droit de soutenance', 'note' => null, 'group' => 'extras', 'amount' => 200000],
                        ],
                        'total' => 1600000,
                    ],
                ],
            ],
        ],
    ],
];
