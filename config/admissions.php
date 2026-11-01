<?php

/*
 * Données de la page d'admission Licence et Master (resources/views/pages/admissions.blade.php).
 * Source : texte des pages « Licence » et « Master » du site actuel + affiches officielles « Admission 2026-2027 » (médiathèque).
 * À METTRE À JOUR CHAQUE ANNÉE (dates, liste du dossier, frais) : voir docs/TODO-CONTENU.md. Rien n'est inventé : en cas d'écart entre
 * le texte du site et l'affiche, c'est l'affiche (plus récente) qui fait foi et l'écart est signalé dans docs/TODO-CONTENU.md.
 */
return [
    'year' => '2026-2027',

    // Dépôt : coordonnées de l'affiche.
    'contact' => [
        'address' => 'BP 325, RN7 Pk 135 Sambaina, Antsirabe 110',
        'email' => 'registrar@zurcher.edu.mg',
        'phones' => ['034 44 931 07', '034 38 180 81', '034 31 653 43'],
    ],

    'levels' => [
        'licence' => [
            'title' => 'Licence',
            'mode' => 'Formation présentielle',
            'schedule' => null,
            'deposit' => 'Auprès de l\'université, par voie postale ou électronique, ou via l\'adresse électronique dédiée (au plus tard une semaine avant la rentrée).',
            'fee' => null,
            // Dates de l'affiche : test d'admission du 1er semestre, date limite de dépôt, rentrée du 2nd semestre.
            'milestones' => [
                ['date' => '2026-10-05', 'label' => 'Test d\'admission', 'hint' => '1er semestre'],
                ['date' => '2026-10-09', 'label' => 'Dépôt du dossier', 'hint' => 'Date limite'],
                ['date' => '2027-03-15', 'label' => 'Rentrée', 'hint' => '2nd semestre'],
            ],
            'documents_title' => 'La chemise jaune',
            'documents' => [
                ['4 photos d\'identité'],
                ['Photocopie certifiée conforme de votre diplôme (Baccalauréat, HSC ou GCE A Level), ainsi que la photocopie certifiée du relevé de notes'],
                ['Une photocopie de la carte d\'identité nationale, du passeport ou, pour les mineurs, de l\'acte de naissance'],
                ['Une chemise cartonnée jaune'],
                ['Un certificat médical attestant de la bonne santé de l\'étudiant'],
                ['Un certificat de résidence, ou un visa d\'études pour les étudiants non ressortissants malgaches'],
                ['Une copie scannée du certificat de bonne santé'],
                ['Deux lettres de recommandation (formulaire à télécharger sur le site web), par deux personnes différentes, avec cachet justificatif, dûment remplies', 'Pour les futurs théologiens, l\'une des lettres doit venir du Pasteur de district et l\'autre du président de la fédération (obligatoire).'],
                ['Une lettre officielle d\'aide financière (pour les étudiants boursiers uniquement)'],
            ],
            'downloads' => ['Formulaire d\'admission', 'Lettre de recommandation'],
            // Après le dépôt (texte de la page Licence).
            'after' => [
                'Analyse de votre dossier.',
                'Le bureau de la registraire vous appelle pour confirmer l\'autorisation de passer l\'examen : gardez votre numéro de téléphone à jour.',
                'Retirez votre lettre d\'autorisation au bureau de la registraire avant l\'heure de l\'examen.',
                'Le jour de l\'examen : venez avec votre reçu du droit d\'admission et votre lettre d\'autorisation.',
            ],
            'mentions_title' => 'Choisir sa mention',
            'mentions_hint' => 'Matières du test d\'admission et séries de Baccalauréat acceptées pour les étudiants malgaches.',
            // formation : intitulé de la mention dans la table des formations (lien vers sa fiche) ; test / series : texte de la page.
            'mentions' => [
                ['name' => 'Gestion', 'formation' => 'Gestion', 'tracks' => ['Management des affaires', 'Finance et comptabilité'], 'test' => 'Français, Mathématiques', 'series' => 'D, C, G2, G3, A2 (note minimale de 10/20 en mathématiques pour le A2)'],
                ['name' => 'Informatique', 'formation' => 'Informatique', 'tracks' => ['Système d\'information et génie logiciel', 'Maintenance et administration de réseau'], 'test' => 'Français, Mathématiques', 'series' => 'D, C, Technique, A2 (note minimale de 10/20 en mathématiques pour le A2)'],
                ['name' => 'Sciences infirmières', 'formation' => 'Sciences infirmières', 'tracks' => ['Infirmerie générale', 'Maïeutique (sage-femme)'], 'test' => 'Français, connaissance de base en sciences (SVT)', 'series' => 'A2, D, C'],
                ['name' => 'Communication', 'formation' => 'Communication', 'tracks' => ['Entreprise', 'Média et Journalisme'], 'test' => 'Français, Anglais', 'series' => 'Toutes séries, avec une note minimale de 10/20 en français'],
                ['name' => 'Études anglophones', 'formation' => 'Études anglophones', 'tracks' => ['Traduction et interprétation', 'Socio-linguistique'], 'test' => 'Français, Anglais (plus un entretien)', 'series' => 'Toutes séries'],
                ['name' => 'Éducation', 'formation' => null, 'tracks' => [], 'test' => 'Français, Anglais', 'series' => 'Toutes séries'],
                ['name' => 'Théologie', 'formation' => 'Théologie', 'tracks' => ['Pastorale', 'Biblique'], 'test' => 'Français, connaissance générale de la Bible, connaissance des doctrines bibliques et de l\'histoire de l\'Église Adventiste du Septième jour, test de niveau d\'informatique', 'series' => 'Toutes séries'],
                ['name' => 'Droit', 'formation' => 'Droit', 'tracks' => ['Public', 'Privé'], 'test' => 'Français, connaissance générale', 'series' => 'Toutes séries'],
            ],
        ],

        'master' => [
            'title' => 'Master',
            'mode' => 'En ligne',
            'schedule' => ['days' => ['Lun', 'Mar', 'Mer', 'Jeu'], 'hours' => '19 h – 21 h', 'text' => 'du lundi au jeudi, de 19 h à 21 h'],
            'deposit' => 'Auprès de l\'université, par voie postale ou électronique, ou via l\'adresse électronique dédiée (au plus tard une semaine avant la rentrée).',
            'fee' => ['amount' => '17 000', 'unit' => 'Ariary', 'text' => 'Frais d\'étude de dossier, à régler par Mobile Money ou virement bancaire.'],
            'milestones' => [
                ['date' => '2026-09-30', 'label' => 'Dépôt du dossier', 'hint' => 'Date limite'],
                ['date' => '2026-10-05', 'label' => 'Rentrée', 'hint' => '1er semestre'],
                ['date' => '2027-03-01', 'label' => 'Rentrée', 'hint' => '2nd semestre'],
            ],
            'documents_title' => 'Le dossier à scanner',
            'documents' => [
                ['Un CV avec photo récente, comprenant votre adresse e-mail et votre numéro de téléphone'],
                ['Une copie scannée du diplôme de Licence, conforme à la spécialité exigée pour la mention de Master choisie'],
                ['Les copies scannées des certificats et relevés de notes des études antérieures'],
                ['Une preuve de paiement des frais d\'étude de dossier d\'un montant de 17 000 Ariary, par Mobile Money ou virement bancaire'],
                ['Une copie scannée de la Carte d\'Identité Nationale (CIN) ou du Passeport'],
                ['Une copie scannée du certificat de résidence, ou un visa d\'études pour les étrangers'],
                ['Une copie scannée du certificat de bonne santé'],
                ['Une lettre de demande d\'admission, rédigée par l\'intéressé et adressée aux responsables du programme de Master'],
                ['Le formulaire de demande d\'admission, dûment rempli et signé (à télécharger sur le site web)'],
            ],
            'downloads' => ['Formulaire de demande d\'admission'],
            'after' => [],
            'mentions_title' => 'Choisir sa mention',
            'mentions_hint' => 'Pour être admis au Master, il faut être titulaire d\'une Licence correspondant à la mention choisie.',
            'mentions' => [
                ['name' => 'Informatique', 'formation' => null, 'tracks' => ['Système d\'information et génie logiciel', 'Maintenance et administration de réseau'], 'requires' => 'Licence en informatique génie logiciel, informatique et télécommunication, électronique, robotique, mathématique appliquée, réseaux informatiques.'],
                ['name' => 'Gestion', 'formation' => null, 'tracks' => ['Finance et comptabilité', 'Management des affaires'], 'requires' => 'Licence en économie, gestion, informatique de gestion, marketing, management, finance, comptabilité, administration.'],
                ['name' => 'Études anglophones', 'formation' => null, 'tracks' => ['Langues étrangères appliquées', 'Linguistique appliquée'], 'requires' => 'Licence en études anglophones, Arts et lettres.'],
            ],
        ],
    ],
];
