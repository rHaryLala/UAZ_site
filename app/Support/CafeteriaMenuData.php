<?php

namespace App\Support;

/**
 * Les trois premiers menus de la cafétéria (documents « MENU CAFETERIA UAZ : 1, 2 et 3 » fournis par l'UAZ), à compter de la semaine du
 * dimanche 8 novembre 2026. Ils vivent ensuite dans le back-office (Cafétéria › Menus de la semaine) : cette classe ne sert qu'à les installer
 * (migration), jamais à les réécrire.
 *
 * Intitulés repris tels quels ; seules les fautes de frappe évidentes sont corrigées (« Sauted » → « Sautéed », « mouse » → « mousse »,
 * « Gros poids » → « Gros pois », « aill+ » → « ail + », « Tamarrin » → « tamarin », « canelle » → « cannelle », mots tout en capitales).
 * Les plats malgaches et les intitulés en anglais sont conservés dans leur langue d'origine.
 *
 * Les documents de l'UAZ sont écrits du mardi au lundi ; le site, lui, commence la semaine le dimanche et la termine le samedi. Chaque menu est donc
 * transcrit ci-dessous tel que le document (mardi → lundi), puis remis dans l'ordre dimanche → samedi : chaque plat garde son jour de la semaine.
 *
 * Chaque semaine : sept jours (du dimanche au samedi), chacun avec « morning », « noon » et « evening ».
 */
final class CafeteriaMenuData
{
    /** @return list<array{starts_on: string, meals: list<array{morning: list<string>, noon: list<string>, evening: list<string>}>}> */
    public static function weeks(): array
    {
        return [
            ['starts_on' => '2026-11-08', 'meals' => self::sundayFirst(self::menu1())],
            ['starts_on' => '2026-11-15', 'meals' => self::sundayFirst(self::menu2())],
            ['starts_on' => '2026-11-22', 'meals' => self::sundayFirst(self::menu3())],
        ];
    }

    /**
     * Remet les sept jours d'un menu écrit « mardi → lundi » dans l'ordre « dimanche → samedi ».
     *
     * @param  list<array{morning: list<string>, noon: list<string>, evening: list<string>}>  $tuesdayFirst
     * @return list<array{morning: list<string>, noon: list<string>, evening: list<string>}>
     */
    private static function sundayFirst(array $tuesdayFirst): array
    {
        // mardi, mercredi, jeudi, vendredi, samedi, dimanche, lundi  →  dimanche, lundi, mardi, mercredi, jeudi, vendredi, samedi
        return array_map(fn (int $i) => $tuesdayFirst[$i], [5, 6, 0, 1, 2, 3, 4]);
    }

    /** @return list<array{morning: list<string>, noon: list<string>, evening: list<string>}> */
    private static function menu1(): array
    {
        return [
            // Mardi
            [
                'morning' => ['Potatoes and boiled leafy greens', 'Bâtard bread with butter', 'Hot chocolate', 'Banana'],
                'noon' => ['Potatoes with green peas', 'Tofu and crunchy vegetables', 'Green salad', 'Chocolate mousse'],
                'evening' => ['Cheese fried noodles', 'Onion quiche', 'Tamarind juice'],
            ],
            // Mercredi
            [
                'morning' => ['Lait de soja avec cannelle', 'Beignet de courgette', 'Bouillon d\'aubergine sauvage', 'Brioche au lait'],
                'noon' => ['Gros pois au poireau', 'Pochet de légume', 'Salade de concombre', 'Macédoine de fruits'],
                'evening' => ['Pomme de terre + morelle noire', 'Mangue'],
            ],
            // Jeudi
            [
                'morning' => ['Maïs au coco', 'Cake à la citrouille', 'Yaourt maison', 'Banane'],
                'noon' => ['Pois mungo', 'Petisse', 'Carotte persillée', 'Salade de courgette', 'Flan au choco (en verre)'],
                'evening' => ['Brède au pistache', 'Cuisse dame'],
            ],
            // Vendredi
            [
                'morning' => ['Citrouille à l\'ail', 'Croissant', 'Thé cannelle'],
                'noon' => ['Haricot rouge', 'Haricot vert avec tofu', 'Salade chou', 'Tarte au potiron', 'Jus framboise'],
                'evening' => ['Hamburger', 'Pomme frite', 'Bouillon de légume'],
            ],
            // Samedi
            [
                'morning' => ['Laisoa saosy', 'Cookies', 'Yaourt maison', 'Dite misy ronono'],
                'noon' => ['Voanjobory', 'Anandrano ritra', 'Bolety vita amin\'ny avoana', 'Lasary karaoty', 'Ranom-boankazo vita amin\'ny papay', 'Mofomamy'],
                'evening' => ['Macaroni', 'Pizza', 'Akondro'],
            ],
            // Dimanche
            [
                'morning' => ['Beignet d\'aubergine', 'Galette', 'Thé soja', 'Pastèque'],
                'noon' => ['Haricot vert sauté', 'Gratin de légumes', 'Salade de concombre', 'Milk-shake d\'avocat (avocat + pomme + lait)'],
                'evening' => ['Légume curry', 'Banane friteuse'],
            ],
            // Lundi
            [
                'morning' => ['Pancake + confiture', 'Citrouille + ail + ciboulette', 'Thé citronnelle'],
                'noon' => ['Haricot blanc', 'Steak de chou-fleur', 'Carotte + persil', 'Jus d\'ananas', 'Esquimaux'],
                'evening' => ['Omelette au poireau', 'Bouillon d\'aubergine amère', 'Banane'],
            ],
        ];
    }

    /** @return list<array{morning: list<string>, noon: list<string>, evening: list<string>}> */
    private static function menu2(): array
    {
        return [
            // Mardi
            [
                'morning' => ['Sautéed green beans', 'Bâtard bread with butter', 'Hot chocolate', 'Banana'],
                'noon' => ['Fried rice', 'Gluten with leafy greens', 'Green salad', 'Fresh radishes', 'Yao'],
                'evening' => ['Vegetable soup', 'Spring rolls', 'Pineapple'],
            ],
            // Mercredi
            [
                'morning' => ['Œufs sur plat', 'Bouillon de brède mafane', 'Pain complet', 'Thé gingembre', 'Pastèque'],
                'noon' => ['Petit pois + tofu', 'Chou farci', 'Salade de concombre', 'Gâteau à la carotte', 'Macédoine de fruits'],
                'evening' => ['Ratatouille + œufs durs', 'Mangue'],
            ],
            // Jeudi
            [
                'morning' => ['Gâteau de banane', 'Yaourt maison', 'Courgette + œufs', 'Banane'],
                'noon' => ['Pois chiche frais + brède mafane', 'Œufs mimosa', 'Petisse', 'Carotte persillée', 'Jus'],
                'evening' => ['Brède au pistache', 'Cuisse dame', 'Thé gingembre'],
            ],
            // Vendredi
            [
                'morning' => ['Œufs sur plat', 'Pomme de terre', 'Gâteau manioc', 'Thé cannelle'],
                'noon' => ['Gratin de macaroni', 'Haricot vert avec tofu', 'Achard chou', 'Tarte à la mangue', 'Jus ananas'],
                'evening' => ['Hamburger', 'Pomme frite', 'Bouillon de légume'],
            ],
            // Samedi
            [
                'morning' => ['Laisoa saosy', 'Ramanonaka', 'Yaourt vita an-trano', 'Dite misy ronono'],
                'noon' => ['Tsaramaso fotsy + anamalao', 'Salady legioma', 'Lasary voatabia + atody masaka mafy', 'Ranom-boankazo', 'Mofomamy'],
                'evening' => ['Misao', 'Taosta', 'Ranom-boankazo'],
            ],
            // Dimanche
            [
                'morning' => ['Maïs au coco', 'Aubergine à l\'ail', 'Poivron farci', 'Beignet de banane', 'Thé soja'],
                'noon' => ['Pochet de légume', 'Flocon d\'avoine', 'Concombre', 'Milk-shake d\'avocat (avocat + pomme + lait)'],
                'evening' => ['Pomme frite', 'Crêpe aux fruits'],
            ],
            // Lundi
            [
                'morning' => ['Pain au raisin', 'Citrouille à l\'ail + ciboulette', 'Thé citronnelle'],
                'noon' => ['Haricot frais + aubergine sauvage', 'Cresson sauté', 'Carotte persillée', 'Pâte à chou', 'Jus tamarin'],
                'evening' => ['Omelette au poireau', 'Bouillon d\'aubergine amère', 'Banane'],
            ],
        ];
    }

    /** @return list<array{morning: list<string>, noon: list<string>, evening: list<string>}> */
    private static function menu3(): array
    {
        return [
            // Mardi
            [
                'morning' => ['Fried eggs (sunny-side up)', 'Rice with leafy greens', 'Baguette bread + butter', 'Soy milk'],
                'noon' => ['Chinese fried rice', 'Soy balls', 'Green salad + soy mayonnaise', 'Strawberry tart', 'Raspberry juice'],
                'evening' => ['Pasta salad', 'Achard bread', 'Banana'],
            ],
            // Mercredi
            [
                'morning' => ['Beignet d\'aubergine', 'Bouillon d\'aubergine sauvage', 'Donut au chocolat'],
                'noon' => ['Haricot rouge + poivron', 'Fromage pané', 'Œufs mimosa', 'Achard de concombre', 'Flan en verre'],
                'evening' => ['Beignet d\'aubergine', 'Bouillon de légume', 'Mangue'],
            ],
            // Jeudi
            [
                'morning' => ['Maïs au coco', 'Cake tracé', 'Yaourt maison', 'Citrouille à l\'ail', 'Banane'],
                'noon' => ['Riz cantonnais', 'Bouillon de brède', 'Fromage pané', 'Carotte persillée', 'Salade de fruits'],
                'evening' => ['Brède au pistache', 'Cuisse dame', 'Thé gingembre'],
            ],
            // Vendredi
            [
                'morning' => ['Œufs durs', 'Pomme de terre au fromage', 'Croissant', 'Thé cannelle'],
                'noon' => ['Voanemba mena', 'Haricot vert avec tofu', 'Salade chou', 'Pâte à chou', 'Jus ananas'],
                'evening' => ['Hamburger', 'Pomme de terre sauce + brède'],
            ],
            // Samedi
            [
                'morning' => ['Laisoa endasina', 'Cookies', 'Yaorta vita an-trano', 'Dite'],
                'noon' => ['Tsaramaso fotsy misy voanio', 'Solon-kena + legioma endasina', 'Salady violety (laisoa mena, betiravy, selery, oliva mainty)', 'Ranom-boankazo amin\'ny voamadilo', 'Mofomamy'],
                'evening' => ['Lasopy legioma', 'Nem', 'Manga'],
            ],
            // Dimanche
            [
                'morning' => ['Omelette au poireau', 'Poivron farci', 'Pain complet', 'Thé soja'],
                'noon' => ['Cresson sauté', 'Beignet légume', 'Concombre + maïs doux', 'Milk-shake d\'avocat (avocat + pomme + lait)'],
                'evening' => ['Légume curry', 'Banane friteuse'],
            ],
            // Lundi
            [
                'morning' => ['« Mofo gasy »', 'Voatavo + ail + ciboulette', 'Thé veromanitra'],
                'noon' => ['Haricot blanc', 'Steak de chou-fleur', 'Carotte + persil', 'Jus de légume (carotte, tomate, citron)', 'Cup cake'],
                'evening' => ['Aubergine à l\'ail', 'Bouillon de légume', 'Crêpe au chocolat'],
            ],
        ];
    }
}
