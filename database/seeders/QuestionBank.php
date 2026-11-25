<?php

namespace Database\Seeders;

/**
 * Demo questions, grouped by notion key: [text, 4 choices, index of the correct one].
 */
final class QuestionBank
{
    /** @return array<string, list<array{0: string, 1: list<string>, 2: int}>> */
    public static function all(): array
    {
        return [
            'variables' => [
                ['Quel type convient pour stocker l\'âge d\'un étudiant ?', ['Réel', 'Entier', 'Booléen', 'Chaîne'], 1],
                ['Après « x ← 5 ; x ← x + 2 », que vaut x ?', ['5', '2', '7', '10'], 2],
                ['Quel type a la valeur « VRAI » ?', ['Booléen', 'Entier', 'Caractère', 'Réel'], 0],
                ['Après « a ← 3 ; b ← a ; a ← 8 », que vaut b ?', ['8', '11', '0', '3'], 3],
                ['Quel est le résultat de 17 mod 5 ?', ['3', '2', '3,4', '12'], 1],
                ['Quelle déclaration est correcte pour une moyenne ?', ['moyenne : entier', 'moyenne : booléen', 'moyenne : réel', 'moyenne : caractère'], 2],
                ['Que fait l\'instruction « lire(n) » ?', ['Elle affiche n', 'Elle saisit une valeur dans n', 'Elle supprime n', 'Elle compare n à 0'], 1],
            ],
            'conditions' => [
                ['Si note = 12, qu\'affiche « si note ≥ 10 alors afficher(Admis) sinon afficher(Ajourné) » ?', ['Ajourné', 'Rien', 'Admis', 'Une erreur'], 2],
                ['Que vaut (VRAI ET FAUX) ?', ['FAUX', 'VRAI', 'Indéfini', '1'], 0],
                ['Que vaut (VRAI OU FAUX) ?', ['FAUX', 'Indéfini', '0', 'VRAI'], 3],
                ['Quelle condition teste si n est pair ?', ['n / 2 = 0', 'n mod 2 = 0', 'n mod 2 = 1', 'n = 2'], 1],
                ['Si x = 4, la condition « x > 2 ET x < 4 » est…', ['vraie', 'fausse', 'impossible à évaluer', 'vraie si x est pair'], 1],
                ['Quelle structure choisit parmi plusieurs valeurs d\'une variable ?', ['tant que', 'pour', 'selon (cas parmi)', 'répéter'], 2],
                ['Que vaut NON (5 > 3) ?', ['VRAI', '5', '3', 'FAUX'], 3],
            ],
            'boucles' => [
                ['Combien de fois s\'exécute « pour i de 1 à 5 » ?', ['4', '5', '6', 'Une infinité'], 1],
                ['Quelle boucle s\'exécute au moins une fois ?', ['répéter … jusqu\'à', 'tant que', 'pour', 'Aucune'], 0],
                ['Après « s ← 0 ; pour i de 1 à 4 : s ← s + i », que vaut s ?', ['4', '8', '10', '24'], 2],
                ['« tant que i < 3 : i ← i + 1 » avec i = 0 au départ : combien de tours ?', ['2', '4', '0', '3'], 3],
                ['Qu\'est-ce qui provoque une boucle infinie ?', ['Un compteur bien initialisé', 'Une condition qui ne devient jamais fausse', 'Une boucle « pour »', 'Un tableau vide'], 1],
                ['Après « p ← 1 ; pour i de 1 à 4 : p ← p × 2 », que vaut p ?', ['8', '16', '4', '2'], 1],
                ['Quelle boucle utiliser quand le nombre de tours est connu à l\'avance ?', ['pour', 'tant que', 'répéter', 'si'], 0],
                ['Boucles imbriquées « pour i de 1 à 3 » et « pour j de 1 à 4 » : combien de tours au total ?', ['7', '4', '12', '3'], 2],
                ['« pour i de 10 à 1 pas -3 » : quelles valeurs prend i ?', ['10, 7, 4, 1', '10, 7, 4', '1, 4, 7, 10', '10, 3'], 0],
                ['Que fait l\'instruction « sortir » (break) dans une boucle ?', ['Elle recommence la boucle', 'Elle arrête le programme', 'Elle saute un tour', 'Elle quitte la boucle'], 3],
                ['« i ← 1 ; tant que i ≤ 10 : i ← i × 2 » : que vaut i à la fin ?', ['10', '8', '16', '32'], 2],
                ['Une boucle « tant que » dont la condition est fausse au départ s\'exécute…', ['0 fois', '1 fois', '2 fois', 'à l\'infini'], 0],
                ['Pour compter les notes ≥ 10 dans une liste, il faut…', ['une boucle et un compteur', 'une seule condition', 'un tableau vide', 'une division'], 0],
            ],
            'tableaux' => [
                ['Dans un tableau T de 10 cases indicé à partir de 0, quel est l\'indice de la dernière case ?', ['10', '9', '11', '1'], 1],
                ['Que vaut T[2] si T = [4, 8, 15, 16] (indices à partir de 0) ?', ['8', '16', '15', '4'], 2],
                ['Quel algorithme trouve le maximum d\'un tableau ?', ['Parcourir et garder la plus grande valeur vue', 'Prendre la première case', 'Prendre la dernière case', 'Trier puis prendre la première case'], 0],
                ['La recherche dichotomique exige un tableau…', ['vide', 'de taille paire', 'd\'entiers positifs', 'trié'], 3],
                ['Combien de cases contient une matrice 3 × 4 ?', ['7', '12', '34', '3'], 1],
                ['Que se passe-t-il en accédant à T[n] dans un tableau de n cases indicé à partir de 0 ?', ['On lit la dernière case', 'On lit la première case', 'Débordement : indice hors limites', 'La case vaut 0'], 2],
            ],
            'relationnel' => [
                ['Qu\'est-ce qu\'une clé primaire ?', ['Un attribut qui identifie une ligne de façon unique', 'Un index facultatif', 'Une colonne toujours numérique', 'Un lien vers une autre table'], 0],
                ['Une clé étrangère sert à…', ['chiffrer une table', 'référencer la clé primaire d\'une autre table', 'trier les lignes', 'supprimer les doublons'], 1],
                ['Une relation « plusieurs à plusieurs » se traduit par…', ['une seule table', 'un attribut multivalué', 'une table d\'association', 'deux clés primaires dans chaque table'], 2],
                ['Dans le modèle relationnel, une ligne d\'une table s\'appelle…', ['un attribut', 'un domaine', 'un schéma', 'un tuple (n-uplet)'], 3],
            ],
            'sql' => [
                ['Quelle requête liste tous les étudiants ?', ['SELECT * FROM etudiant;', 'GET etudiant;', 'SHOW * etudiant;', 'LIST etudiant;'], 0],
                ['Quel mot-clé filtre les lignes ?', ['ORDER BY', 'WHERE', 'GROUP BY', 'FROM'], 1],
                ['Quelle fonction compte le nombre de lignes ?', ['SUM()', 'MAX()', 'COUNT()', 'LEN()'], 2],
                ['Quel mot-clé trie les résultats ?', ['SORT', 'GROUP BY', 'HAVING', 'ORDER BY'], 3],
            ],
        ];
    }
}
