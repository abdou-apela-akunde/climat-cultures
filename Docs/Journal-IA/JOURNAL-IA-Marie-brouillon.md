# Journal d'usage de l'IA — Marie ETAMANDO (DATA 3) — BROUILLON À COMPLÉTER

> Ces lignes décrivent ce qui s'est réellement passé dans nos échanges. **Relis-les et corrige-les** : le journal est nominatif et noté (C7). Complète surtout la colonne « Justification » avec ce que **tu** as vérifié, compris ou corrigé. Remplace tout texte entre crochets. Ne laisse pas une ligne dire que tu as vérifié quelque chose que tu n'as pas vérifié.

## Déclaration permanente — assistants ambiants
| Équipier | Outil ambiant | Où | Depuis |
|---|---|---|---|
| Marie ETAMANDO | [à compléter : « Aucun » ou nom de l'outil] | [à compléter] | [à compléter] |

## Journal

| Date | Équipier | Outil/modèle | Tâche / contexte | Prompt (résumé) | Sortie IA | Gardé/modifié/rejeté | Justification (vérif. / correction / test) | Tokens (≈) |
|---|---|---|---|---|---|---|---|---|
| 08/10 | Marie | Claude (Sonnet) | Comprendre granularité, clé primaire, UNIQUE, origine des indicateurs | « explique-moi en détail la granularité, ce qui empêche les doublons, d'où viennent jours_gel… » | explications pédagogiques + exercice | **gardé** | J'ai reformulé moi-même : la PK ne regarde pas le contenu, il faut une contrainte UNIQUE ; on stocke `jours_gel` plutôt que les températures pour limiter le volume et éviter de rappeler l'API | non mesurable |
| 08/10 | Marie | Claude (Sonnet) | Choix horizon / année dans `Dim_Temps` | « pour répondre à la vigne à Reims aujourd'hui vs 2050 il me faut la colonne horizon » | explication année (grain fin) + horizon (regroupement), limite de l'API au 01/01/2050 | **gardé** | [à compléter : ce que j'ai compris] |  non mesurable |
| 10/10 | Marie | Claude (Sonnet) | `Dim_Modele` et test de l'API Open-Meteo | réponse à l'exercice `Dim_Modele`, question sur le risque | correction du rôle de la dimension ; explication de `nom_modele` = identifiant exact de l'API ; consigne de test par URL | **modifié** | J'ai **lancé moi-même** les deux URL dans mon navigateur (modèles `MRI_AGCM3_2_S` et `EC_Earth3P_HR`, Le Plessis-Trévise, 2049) : les deux renvoient des données ; j'ai remarqué l'arrondi à la maille de grille (48.800 / 2.600, 111 m) | non mesurable |
| 10/10 | Marie | Claude (Sonnet) | Tableau J1 « dimensions + rôle + points à confirmer » | « fais le tableau » | tableau rédigé par l'IA | **modifié** | Relu, mention de brouillon retirée, résultat du test MRI ajouté, transmis au pôle DATA ; [à compléter : ce que j'ai reformulé] | non mesurable |
| 12/10 | Marie | Claude (Sonnet) | Scripts CREATE TABLE `Dim_Commune`, `Dim_Modele`, `Dim_Temps` | j'ai écrit `Dim_Modele` et `Dim_Temps` moi-même ; exemple fourni pour `Dim_Commune` | corrections : parenthèses/virgules, longueur du VARCHAR, UNIQUE sur `nom_modele` et `annee` (pas sur l'id) | **modifié** | J'ai exécuté les scripts dans phpMyAdmin et vérifié que les 3 tables existent ; [à compléter : test d'insertion en double] | non mesurable |
| 16/10 | Marie | Claude (Sonnet) | `Fact_Indicateur` (clés étrangères, UNIQUE) puis colonnes d'indicateurs | squelette à compléter ; mon ALTER TABLE | corrections : casse des noms de tables (Linux), `DECIMAL` sans précision (perd les décimales), syntaxe des types | **modifié** | J'ai complété les 6 blancs du squelette (types, clés étrangères, UNIQUE sur 4 colonnes) ; [à compléter : exécution et vérification dans phpMyAdmin] | non mesurable |
| 17/10 | Marie | Claude (Sonnet) | Finalisation J2 pendant ma maladie : script RGPD + remplissage des dimensions, document de conception, contrat v0.1, brouillon de ce journal | « fini tout ce qu'il me restait à faire » | scripts `02_rgpd_et_peuplement.sql`, `J2-conception-DATA3.md`, ce brouillon | **[à compléter : gardé / modifié]** | [à compléter honnêtement : ce que j'ai relu, exécuté et testé dans phpMyAdmin (BLOC 1 à 6), ce que j'ai compris, ce que j'ai modifié] ; ces fichiers ont été rédigés par l'IA et non par moi, je dois pouvoir les expliquer à la soutenance | non mesurable |

## Synthèse environnementale (rendu final)
Les tokens ne sont pas exposés dans mon usage : « non mesurable » (à écrire dans le périmètre de la mesure).
