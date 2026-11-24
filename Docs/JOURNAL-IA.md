# 📓 Journal d'usage de l'IA — Équipe 7

> **SAÉ 3.01 « Climat & Cultures »** — à tenir **au fil de l'eau**, versionné dans le dépôt Git, annexé au livrable final.
> Membres : Abdelwadoud ALLOUNE, Abdou APELA AKUNDE, Aicha DABO, Amina DJAYJI, Marie-Chrislaine ETAMANDO

## Rappel express des règles
- L'IA est un **copilote, pas un sous-traitant**. 🔑 *« Si tu ne peux pas l'expliquer, tu ne peux pas le rendre. »*
- **Tout outil est autorisé** (Claude fourni, ChatGPT, Gemini, Copilot…) — **à condition d'être déclaré**.
- **Une ligne par usage significatif** (code/requête/texte intégré, debug qui change la solution, choix orienté, tests, refactor).
- **Nominatif** : chaque ligne identifie son auteur.
- **Jamais** de données personnelles dans les prompts.
- La colonne **Justification** est le cœur : *pourquoi* + ce que vous avez **vérifié / corrigé / testé**.

---

## ⌨️ Déclaration permanente — assistants ambiants

> **À remplir en J0, une fois pour toutes.** Un assistant d'autocomplétion (Copilot, Tabnine, IA intégrée à l'IDE…) produit des suggestions **en continu** : impossible de les journaliser une par une. On le déclare donc **globalement** ici.
> ⚠️ La règle d'or continue de s'appliquer : ce que vous intégrez, vous devez savoir l'expliquer.

| Équipier | Outil ambiant utilisé | Où (IDE / éditeur) | Depuis quand |
|---|---|---|---|
| Abdou APELA AKUNDE | `[Aucun, ou nom de l'outil à préciser]` | `[VS Code ?]` | `[…]` |
| Abdelwadoud ALLOUNE | `[à remplir par lui]` |  |  |
| Marie-Chrislaine ETAMANDO | Aucun | Aucun | Sans objet |

*Aucun assistant ambiant utilisé ? Écrivez-le explicitement : « Aucun » — une case vide n'est pas une déclaration.*

---

## Journal

| Date | Équipier | Outil/modèle | Tâche / contexte | Prompt (résumé) | Sortie IA | Gardé/modifié/rejeté | Justification (vérif. / correction / test) | Tokens (≈) |
|---|---|---|---|---|---|---|---|---|
| 10/10 | Abdou APELA AKUNDE | Claude (claude.ai) | Test de l'API Open-Meteo et export CSV | « donne-moi la commande » ; erreur sur `reponse.json` | Commande curl, script `convertir.py` | modifié | Commande lancée par moi : la version sans clé a fonctionné. Erreur de chemin (`FileNotFoundError`) corrigée en lançant le script depuis le bon dossier. Faute dans l'URL proposée par Claude (`climat-api` au lieu de `climate-api`) repérée et corrigée. Coordonnées de Paris pour être cohérent avec le code INSEE 75056. CSV vérifié à l'ouverture | non mesurable |
| 10/10 | Abdou APELA AKUNDE | Claude (claude.ai) | Déploiement d'une page statique sur AlwaysData | Erreurs SSH et `git clone` | Procédure SSH, token GitHub, déclaration du site | gardé | Hôte SSH corrigé (préfixe `ssh-`). Page affichée à l'adresse du site, vérifiée dans le navigateur | non mesurable |
| 08/10 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Comprendre la granularité, la clé primaire, la contrainte UNIQUE et l'origine des indicateurs | « explique-moi en détail la granularité, ce qui empêche d'avoir deux fois la même ligne, d'où viennent les jours de gel » | Explications pédagogiques et exercice | gardé | J'ai reformulé moi-même : la clé primaire ne regarde pas le contenu de la ligne, c'est la contrainte UNIQUE sur la combinaison des clés qui évite les doublons ; on stocke les jours de gel plutôt que les températures brutes pour limiter le volume et éviter de rappeler l'API | non mesurable |
| 08/10 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Choix entre année et horizon dans `Dim_Temps` | J'ai formulé le besoin : comparer la vigne à Reims aujourd'hui et en 2050 impose une colonne horizon | Explication année (grain fin) et horizon (regroupement), limite de l'API au 01/01/2050 | gardé | Le besoin et le choix de garder l'horizon viennent de moi ; l'IA a ajouté la limite de l'API, que j'ai retrouvée dans l'énoncé | non mesurable |
| 10/10 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Rôle de `Dim_Modele` et test de l'API Open-Meteo | Ma réponse à l'exercice sur `Dim_Modele`, puis demande de procédure de test | Correction du rôle (une dimension décrit un axe d'analyse, elle n'affiche rien) ; `nom_modele` = identifiant exact de l'API ; deux URL de test | modifié | J'ai lancé moi-même les deux URL dans mon navigateur (`MRI_AGCM3_2_S` et `EC_Earth3P_HR`, Le Plessis-Trévise, année 2049) : les deux renvoient des données ; j'ai constaté que l'API répond avec la maille de grille (48.800 ; 2.600, altitude 111 m) et non avec la commune exacte | non mesurable |
| 10/10 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Tableau J1 « dimensions, rôle, points à confirmer » | « fais le tableau pour moi » | Tableau rédigé par l'IA | modifié | Je l'ai relu, j'ai fait retirer la mention de brouillon et ajouter le résultat du test de `MRI_AGCM3_2_S`, puis je l'ai transmis au pôle DATA | non mesurable |
| 12/10 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Scripts CREATE TABLE de `Dim_Commune`, `Dim_Modele`, `Dim_Temps` | Exemple fourni pour `Dim_Commune` ; j'ai écrit `Dim_Modele` et `Dim_Temps` moi-même | Corrections : parenthèses et virgules, longueur du VARCHAR, UNIQUE sur `nom_modele` et sur `annee` (et non sur l'identifiant) | modifié | J'ai exécuté les scripts dans phpMyAdmin ; j'ai testé l'insertion du même modèle deux fois : la contrainte UNIQUE a refusé le doublon ; j'ai supprimé les lignes de test | non mesurable |
| 16/10 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | `Fact_Indicateur` : clés étrangères, UNIQUE sur 4 colonnes, colonnes d'indicateurs | Squelette à compléter (6 blancs) puis mon ALTER TABLE | Corrections : casse des noms de tables (sensible sous Linux), `DECIMAL` sans précision qui supprime les décimales, syntaxe des types | modifié | J'ai complété les 6 blancs (types, clés étrangères, UNIQUE) ; le 20/11 j'ai vérifié dans phpMyAdmin (`SHOW COLUMNS`) que la table existe avec les bonnes colonnes et `decimal(8,1)` pour `gdd` et `bilan_hydrique` | non mesurable |
| 17/10 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Finalisation du J2 pendant ma maladie : script de colonne RGPD et remplissage, document de conception, brouillon de journal, dépôt sur la branche `data3-integration` | « fini tout ce qu'il me restait à faire » | `02_rgpd_et_peuplement.sql`, `J2-conception-DATA3.md`, brouillon de journal, trois commits | rejeté en grande partie | Rédigés par l'IA. Je ne les ai ni relus ni exécutés à ce moment-là, et le 20/11 la base montre que le script `02` n'a jamais été exécuté (pas de colonne `id_session`, dimensions vides). L'équipe a livré sa propre version sur la branche `data`, que j'ai retenue comme référence | non mesurable |
| 20/11 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Corrections J2 : écarts entre notre modèle et celui de l'équipe (grain, RGPD, années des horizons) | « on commence par les corrections nécessaires » ; demande d'explications détaillées sur chaque choix | Comparatif des options, explication des fenêtres de 30 ans, message pour DEV | gardé, avec mes choix | J'ai vérifié l'état réel de la base AlwaysData par trois requêtes (5 tables présentes, `id_session` absent, dimensions vides). J'ai choisi le grain par horizon (formule de l'énoncé : commune × culture × horizon × modèle), l'identité RGPD dans `Fact_Indicateur` comme l'énoncé, et les fenêtres 1990-2019 et 2020-2049. J'ai reformulé moi-même la raison de la moyenne sur 30 ans : les résultats varient beaucoup d'une année à l'autre, la moyenne lisse les années exceptionnelles | non mesurable |
| 21/11 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Schéma MariaDB aligné sur nos décisions, après le choix de l'équipe du calcul à la demande | « on a choisi le mode de calcul à la demande »  | Script `03_schema_mariadb.sql` (5 tables, grain par horizon, `id_session` dans `Fact_Indicateur`), rédigé par l'IA à partir du script SQLite de l'équipe ; explication de ce qu'il faut savoir expliquer et de ce qu'il faut savoir écrire | gardé | Je n'ai pas écrit ce script. J'ai confirmé que le calcul à la demande rend cohérent mon choix de mettre `id_session` dans `Fact_Indicateur`.  je l'ai exécuté moi-même le 24/11 | non mesurable |
| 23/11 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Vérification avant suppression (BLOC 0) et suppression des anciennes tables (BLOC 1) | J'ai expliqué le but du BLOC 0, puis j'ai écrit moi-même la requête de comptage et les six `DROP TABLE` | Corrections : parenthèses des sous-requêtes non fermées, casse des noms de tables (`Dim_commune` au lieu de `Dim_Commune`), `IF EXIST` au lieu de `IF EXISTS`, nom `fact_indicateurs` erroné | modifié | Le 24/11 j'ai exécuté mes versions corrigées : compteurs à 0 sur les 5 tables avant suppression, six `DROP` sans erreur (note `#1051` normale pour `Ref_Source_Seuil`, jamais créée), `SHOW TABLES` vide ensuite | non mesurable |
| 24/11 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Création des tables (blocs 2 et 3), remplissage (bloc 4) et tests des contraintes sur AlwaysData | J'ai exécuté les blocs un par un et collé chaque résultat | Blocs 2 à 4 rédigés par l'IA ; une phrase non prouvée dans la note de test d'`EC_Earth3P_HR` (« réponses vides déjà observées ») a été retirée | modifié | Exécuté par moi : 5 tables, puis `SHOW CREATE TABLE Fact_Indicateur` (clé `uq_fact` sur 5 colonnes, 4 clés étrangères) ; compteurs 16 / 10 / 2 / 3 ; Briançon `05023` conservé ; doublon INSEE refusé (`#1062`) ; commune inexistante refusée (`#1452`) ; `UPDATE` de la note d'`EC_Earth3P_HR` passé ; test `sessionA` / `sessionB` : même simulation acceptée pour deux sessions, refusée pour la même session, effacement de A sans toucher B ; table vidée à la fin | non mesurable |
| 25/11 | Marie-Chrislaine ETAMANDO | Claude (claude.ai, famille Sonnet) | Note de décisions (grain, RGPD, horizons), dépôt du script et nettoyage de la branche `data3-integration` | Demande d'explications détaillées sur les trois décisions, puis mes trois réponses écrites | Explications ; mise en forme de ma note `DECISIONS-DATA3.md` à partir de mes phrases ; script `03` et note poussés sur ma branche, scripts périmés `01` et `02` retirés à ma demande | gardé | Le contenu des trois décisions vient de mes réponses ; l'IA a corrigé les noms (`Fact_Indicateur`, `id_session`) et ajouté la liste des preuves que j'avais exécutées. J'ai fait corriger mon imprécision sur le test : c'est la 3e requête qui a échoué, pas la 2e session | non mesurable |

*(Ajoutez autant de lignes que nécessaire.)*

---

## 🌱 Synthèse environnementale (à compléter pour le rendu final)

> Méthode et exemple de calcul : voir `poc-empreinte-ia.py`. Manier avec **esprit critique** (forte incertitude).

- **Total tokens** sur le projet : `[…]` (entrée : `[…]` / sortie : `[…]`)
- **Énergie estimée** : `[…]` Wh
- **CO₂e estimé** : `[…]` g
- **Équivalence parlante** : ≈ `[…]` (ex. m/km en voiture, via facteur ADEME)
- **Hypothèses retenues** : `[Wh/1k tokens, intensité carbone du réseau, modèle…]`
- **Périmètre de la mesure** : `[quels usages ai-je pu compter, lesquels non ?]`
  > ℹ️ La colonne **Tokens** n'est renseignable que pour le compte API fourni. Un usage sur ChatGPT, Gemini ou Copilot n'expose pas ses tokens : **écrivez « non mesurable »** dans la colonne et dites-le ici. Annoncer ce qu'on n'a **pas** pu mesurer fait partie d'une mesure honnête — c'est valorisé, pas pénalisé.
- **Discussion de l'incertitude** : `[pourquoi ces chiffres varient d'un facteur ~10 ?]`
- **Regard coût/bénéfice** : `[cet usage de l'IA en valait-il la peine ? où aurait-on pu être plus sobre ?]`
