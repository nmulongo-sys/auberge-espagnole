# Pique-nique — auberge espagnole

Application web de coordination de pique-nique en auberge espagnole : inscription par icônes kawaii, menu commun par catégorie, gestion du matériel par tête, grille de présence et vue chevauchements.

![Aperçu des icônes kawaii](apercu-icones.png)

**En ligne** : ouvrir `pique-nique.html` dans un navigateur, ou héberger sur InfinityFree / Synology Web Station (voir Déploiement).  
**Démo en ligne (GitHub Pages)** : https://nmulongo-sys.github.io/auberge-espagnole/ — ⚠️ **mode local uniquement** (localStorage) : l'hébergement statique de Pages n'exécute pas `api.php`, donc pas de synchronisation partagée entre participants. Pour le mode partagé multi-contributeurs, conserver un hébergement PHP (InfinityFree / Synology).  
**Statut** : révision 2026-07-02 · trois fichiers (`pique-nique.html` + `api.php` + `data.json`) · aucune dépendance externe hormis la police Fraunces (Google Fonts, avec repli système hors-ligne).

---

## Utilisation

Aucune installation. Ouvrir `pique-nique.html` dans n'importe quel navigateur moderne (mobile ou desktop). L'app est organisée en trois onglets :

| Onglet | Public | Ce qu'on y fait |
|---|---|---|
| **Participer** | Participants | Inscription à icônes kawaii : prénom, nombre de personnes, ce qu'on apporte (7 items cliquables), horaires en repli |
| **Récapitulatif** | Tous | Menu commun par catégorie, couverture matériel, grille de présence avec vue chevauchements |
| **Pilotage** | Organisateur | Configuration de l'événement, gestion des participants, export/import JSON |

Un **guide participants** autonome est fourni séparément : `mode-emploi-auberge-espagnole.html`.

---

## Déploiement

Trois fichiers à placer dans le même dossier (`htdocs/` sur InfinityFree, `web/` sur Synology Web Station) :

```
htdocs/
├── pique-nique.html          ← l'app
├── api.php                   ← backend de stockage partagé
└── data.json                 ← à créer manuellement (voir ci-dessous)
```

**Créer `data.json` manuellement** via le File Manager d'InfinityFree (le script PHP peut modifier un fichier existant mais pas en créer un) :

```json
{"event":{"titre":"Pique-nique — auberge espagnole","date":"","lieu":"","debut":"12:00","fin":"16:00","note":"","attendus":""},"people":[],"items":[]}
```

Puis lui donner les droits **666** (clic droit → Permissions/Chmod → 666).

Le pied de page de l'app affiche le mode de stockage actif en temps réel (🟢 PHP partagé / 🟡 localStorage local). En cas de doute, ouvrir la Console navigateur : les erreurs d'écriture PHP sont retournées en JSON explicite.

---

## Architecture & conventions

### Structure du fichier unique (`pique-nique.html`)

```
<head>         Polices + palette CSS + tous les styles (~ 200 lignes)
<body>         HTML des trois pages (#page-participer, #page-recap, #page-pilotage)
               + dialog .copybox (export WhatsApp)
<script>       JS en ordre logique :
               1. Stockage (store)
               2. Constantes (catégories, SIMPLE_ITEMS, SVG kawaii)
               3. État (blankState, normalizeState, mutate)
               4. Temps (toMin, toHHMM, slots, windowOf, overlap)
               5. Rendu inscription (renderItemGrid, renderPeople, renderTimeChips)
               6. Rendu récap (renderMenu, renderConvives, renderGrid)
               7. Rendu pilotage (renderAdmin)
               8. Formulaire avancé (addApportRow, updateRowHint, refreshHints)
               9. Édition / enregistrement (loadForEdit, readForm, saveParticipation)
               10. Export texte WhatsApp (buildGraphicText, copyShare)
               11. Utils (toast, showTab, bindEvent, init)
```

### Modèle de données (`state`)

```js
{
  v: 2,                       // version du schéma
  event: {
    titre, date, lieu,        // textes libres
    debut, fin,               // "HH:MM", créneaux 30 min
    note, attendus            // attendus : nb cible pour la couverture matériel
  },
  people: [
    { id, nom, arrivee, depart, tetes }
    // arrivee / depart : "HH:MM" ou "" (= jusqu'à la fin)
    // tetes : entier ≥ 1 (toi compris)
  ],
  items: [
    { id, personId, categorie, plat, qty }
    // qty : entier > 0 ou null (non quantifié)
    // categorie : valeur de CAT_FOOD ou CAT_MAT
  ]
}
```

Clé `localStorage` (mode dégradé) : `"pique-nique:data"`.

### Couche de stockage — cascade 4 niveaux

```
1. window.storage partagé  → artefact Claude (shared:true)
2. api.php                 → hébergement auto-détecté (fetch ./api.php)
3. localStorage            → fallback par navigateur
4. _mem (objet JS)         → dernier recours (session only)
```

La variable `phpOk` (null / true / false) est définie dès le premier `store.get()` et utilisée dans le polling et le pied de page.

`mutate(fn)` est la seule fonction qui écrit l'état : elle relit d'abord l'état frais depuis le store avant d'appliquer `fn`, ce qui limite les écrasements entre deux contributeurs simultanés (verrouillage optimiste).

### Catégories

```js
CAT_FOOD  // 10 catégories nourriture + boissons
CAT_MAT   // 8 catégories matériel

PER_PERSON_MAT = ["Verres & gobelets","Assiettes","Couverts","Chaises & sièges"]
// → ratio affiché : total apporté / headcount().effective
// → signalé en rouge si insuffisant

OTHER_ESS_MAT  = ["Sacs poubelle"]
// → signalé absent, sans ratio

ESS_FOOD = ["Plat principal","Salade","Pain & tartinades","Dessert","Boisson sans alcool"]
// → signalé absent dans "À compléter"
```

### Interface à icônes kawaii (onglet Participer)

```js
SIMPLE_ITEMS   // 7 items fixes : chips, amuse, soft, alc, verres, serv, chaises
               // chaque item porte : key, emoji (repli), label, categorie, plat

SIMPLE_LINES   // disposition en 3 lignes : [chips,amuse] / [soft,alc] / [verres,serv,chaises]

SIMPLE_SVG     // 7 SVG inline kawaii (64×64 px, visage + joues roses)
               // rendu identique sur tous appareils, hors-ligne

PERSON_SVG     // mini-personnage kawaii (24×32 px) répété N fois = "vous venez à N"
PLUS_SVG       // bouton "5+" (disque jaune avec croix)

counts         // { key: n } — état local du formulaire, n = nombre de taps
```

Un tap sur une icône incrémente `counts[key]`. Le badge `×N` et le bouton `−` apparaissent à partir de `n ≥ 1`. La conversion vers `state.items` se fait à l'enregistrement via `readForm()`.

### Grille de présence

- `windowOf(p)` → `[debut_min, fin_min]` (la fin de l'event si `p.depart` vide)
- `cellsList()` → tableau des débuts de tranches de 30 min (sans la dernière)
- `overlap(p1,p2)` → `[start,end]` ou `null`
- Classe CSS `.ov` = chevauchement avec la personne focus ; `.dim` = pas de chevauchement ; `.focus` = personne sélectionnée

### Calcul des convives

```
headcount().effective = Math.max(attendusSaisi, somme_des_tetes)
```
— plancher : on ne peut jamais attendre moins que les inscrits confirmés.

---

## Journal de développement

### 2026-07-02 — Version initiale documentée

- Conception et développement complets sur cette session de conversation.
- Trois onglets : **Participer** (inscription), **Récapitulatif** (menu + grille), **Pilotage** (config + admin).
- Couche de stockage 4 niveaux : Claude shared → `api.php` → localStorage → mémoire.
- `api.php` avec lecture/écriture atomique (`LOCK_EX`) ; instructions InfinityFree intégrées (création manuelle de `data.json`, chmod 666).
- Interface d'inscription à icônes kawaii : 7 SVG inline (chips, amuse-gueule, boisson sans alcool, boisson alcoolisée, verres, serviettes, chaises), dessinés à la main (64×64 px, style pastel, visage + joues roses).
- Compteur de personnes : miniature kawaii `PERSON_SVG` répétée 1 à 4 fois + bouton `5+` incrémental.
- Champ « tu viens à combien ? » : `tetes` par personne, headcount = `max(attendus, Σtetes)`.
- Couverture matériel par tête : ratio `apporté / effective` affiché en vert/rouge pour `PER_PERSON_MAT`.
- Grille de présence avec sélecteur de chevauchements (overlap calculé sur `[arrivee, depart]`).
- Horaires d'arrivée et départ en repli (▾) dans l'onglet Participer pour ne pas surcharger l'interface non-technophile.
- Export WhatsApp texte enrichi (emojis par catégorie, totaux matériel, section ⚠ manques).
- Modification d'une participation : rechargement du formulaire depuis l'état, `counts` reconstruits depuis `state.items`, autres items en formulaire avancé.
- Guide participants autonome fourni séparément : `mode-emploi-auberge-espagnole.html`.
- Polling toutes les 6 s (mode PHP ou Claude) pour synchroniser plusieurs contributeurs simultanés.

---

## Licence

Code produit en collaboration avec Claude (Anthropic). Tous droits réservés — usage personnel. Libre de réutilisation pour tout usage non commercial avec mention de l'auteur original.
