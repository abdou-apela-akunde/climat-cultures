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

*Aucun assistant ambiant utilisé ? Écrivez-le explicitement : « Aucun » — une case vide n'est pas une déclaration.*

---

## Journal

| Date | Équipier | Outil/modèle | Tâche / contexte | Prompt (résumé) | Sortie IA | Gardé/modifié/rejeté | Justification (vérif. / correction / test) | Tokens (≈) |
|---|---|---|---|---|---|---|---|---|
| 10/10 | Abdou APELA AKUNDE | Claude (claude.ai) | Test de l'API Open-Meteo et export CSV | « donne-moi la commande » ; erreur sur `reponse.json` | Commande curl, script `convertir.py` | modifié | Commande lancée par moi : la version sans clé a fonctionné. Erreur de chemin (`FileNotFoundError`) corrigée en lançant le script depuis le bon dossier. Faute dans l'URL proposée par Claude (`climat-api` au lieu de `climate-api`) repérée et corrigée. Coordonnées de Paris pour être cohérent avec le code INSEE 75056. CSV vérifié à l'ouverture | non mesurable |
| 10/10 | Abdou APELA AKUNDE | Claude (claude.ai) | Déploiement d'une page statique sur AlwaysData | Erreurs SSH et `git clone` | Procédure SSH, token GitHub, déclaration du site | gardé | Hôte SSH corrigé (préfixe `ssh-`). Page affichée à l'adresse du site, vérifiée dans le navigateur | non mesurable |

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
