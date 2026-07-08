# Partage du spectre *(titre de travail — à confirmer)*

Outil pédagogique **en ligne et collaboratif en temps réel** de composition de patterns rythmiques élémentaires : chaque participant choisit son instrument, crée des « briques » sonores classées par hauteur, et les place sur une grille commune. La contrainte de jeu — deux briques ne peuvent pas occuper la même zone du spectre au même instant — apprend aux musiciens à **partager le spectre**. À la fin, chacun reçoit son pattern individuel et l'ensemble se joue en polyrythmie.

**En ligne** : *à venir — GitHub Pages, dépôt dédié à créer (fichiers hébergés provisoirement dans le dossier `partage-du-spectre/` du dépôt `auberge-espagnole`).*
**Statut** : prototype v0 fonctionnel — fichier HTML unique + Supabase (Realtime) ; schéma de base de données en place.

## Utilisation

1. Ouvrir `index.html` et renseigner en tête de script `SUPABASE_URL` et `SUPABASE_ANON_KEY` (placeholders `<VOTRE_URL>` / `<VOTRE_CLE_ANON>` — valeurs dans Supabase → Settings → API). Sans configuration, la page affiche un écran d'aide.
2. Créer ou rejoindre une **salle** par code partagé (code de 5 caractères généré à la création ; temps par cycle 2–16 et tempo choisis à la création).
3. Choisir son instrument et créer ses **briques** : une frappe (p. ex. claqué de djembé), classée dans une bande de hauteur, avec une épaisseur spectrale. Bouton « frappes types » pour partir des briques suggérées de l'instrument ; éditeur pour créer une **variante par décalage de hauteur** (± 12 demi-tons) d'une frappe existante.
4. Placer ses briques sur la grille commune — tout le monde voit tout, instantanément. Les conflits de spectre sont refusés (vérification locale + contrainte serveur).
5. Phase de jeu : lecture Web Audio de la polyrythmie complète (bouton ▶, curseur de lecture, briques qui clignotent) + « Mon pattern » : récupération du pattern individuel de chacun (grille texte copiable + écoute solo).

## Architecture & conventions

### Décisions de conception (figées le 2026-07-08)

- **Axe horizontal (temps)** : cycle de N temps configurable librement (2 à 16 — binaire, ternaire via 6/12, métriques impaires). Chaque temps porte **4 positions** : *juste avant* / *temps* / *juste après* / *contre-temps*. Soit `colonnes = N × 4` (max 64).
- **Axe vertical (spectre)** : **8 bandes**, du grave profond (0) au claqué/brillance (7). Granularité choisie par calcul de densité : ~4 joueurs × ~8 briques sur 16 colonnes ≈ 2 briques/colonne ; 8 bandes donnent une densité de conflit qui mord sans paralyser (~25–40 % de remplissage final).
- **Épaisseur spectrale** : chaque brique occupe **1 à 3 bandes** (`band` = bande basse, `band + thickness ≤ 8`). Un dunun étouffé = 1 bande, un tone = 2, un claqué large bande = 3. Une brique épaisse coûte plus d'espace : la leçon d'orchestration est encodée dans la règle.
- **Règle de partage** : **blocage strict** des conflits. Garanti côté serveur par une contrainte d'exclusion Postgres (`EXCLUDE USING gist` sur `(room_code, col, int4range(band, band+thickness))` avec `btree_gist`) — aucun chevauchement possible, même en cas de placement simultané. Les plages `int4range` sont semi-ouvertes : deux briques verticalement adjacentes (p. ex. `[2,4)` et `[4,5)`) cohabitent sans conflit.
- **Simultanéité** : Supabase Realtime (publication `supabase_realtime` sur les trois tables).
- **Son** : synthèse Web Audio paramétrique, sans échantillon — moteur inspiré du dépôt `metronome`, réimplémenté en quatre voix (`percDrum` membrane, `percSnap` claqué, `percBell` métal, `percScrape` frotté) ; timbres djembé basse/tone/slap, dununba, sangban, kenkeni, cloche, agogô, surdo, reco-reco. Le **décalage de hauteur** d'une brique = multiplication des fréquences de synthèse (`sound.mult = 2^(demi-tons/12)`).
- **Positions temporelles → timing de lecture** : décalages en fraction de temps `[-0,10 ; 0 ; +0,10 ; 0,5]` pour *avant* / *temps* / *après* / *contre-temps* ; un « juste avant » du temps 1 boucle en fin de cycle.

### Modèle de données (Supabase — **créé le 2026-07-08**, projet `Music Noel`)

| Table | Rôle | Colonnes clés |
|---|---|---|
| `poly_rooms` | salles | `code` (PK, 4–8 alphanum.), `beats` (2–16), `tempo` (30–300), `phase` (`compose`/`play`) |
| `poly_players` | participants | `id` uuid, `room_code` FK (cascade), `name`, `instrument`, `color`, `palette` jsonb (définitions de briques) |
| `poly_bricks` | briques placées | `id` uuid, `room_code`, `player_id` FK (cascade), `col` (0–63), `band` (0–7), `thickness` (1–3), `label`, `sound` jsonb (paramètres de synthèse) |

- Contrainte d'exclusion `poly_bricks_no_overlap` : `EXCLUDE USING gist (room_code WITH =, col WITH =, int4range(band, band + thickness) WITH &&)`.
- RLS activée, politiques permissives sur clés `anon`/`authenticated` : le code de salle fait office de secret partagé (même modèle que « Vacances entre nous »).
- Les trois tables sont dans la publication `supabase_realtime`.
- Migrations appliquées : `poly_partage_du_spectre_schema`, `poly_rls_and_realtime`.
- Le client renseigne l'URL du projet Supabase et la clé `anon` en tête de script (placeholders `<VOTRE_URL>` / `<VOTRE_CLE_ANON>` dans le source public).

### Réutilisation inter-dépôts

- `metronome` → moteur de synthèse percussive (voir ci-dessus).
- `verrevacances` → patron d'intégration Supabase en fichier HTML unique (CDN `@supabase/supabase-js@2`, garde de configuration, écran d'aide si clés absentes).

## Journal de développement

### 2026-07-08 (2) — Déblocage Supabase, schéma créé, prototype v0
- **Blocage levé** : la migration `poly_partage_du_spectre_schema` (3 `CREATE TABLE` + contrainte d'exclusion) est passée sans erreur via `apply_migration` — l'échec opaque de la veille n'a pas été reproduit, cause toujours non identifiée (probable incident transitoire côté connecteur).
- Schéma complet en place : 3 tables, RLS + politiques permissives, publication Realtime (`poly_rls_and_realtime`).
- **Contrainte d'exclusion validée par test** : brique adjacente verticalement acceptée (plages semi-ouvertes), brique en chevauchement rejetée (`23P01 conflicting key value violates exclusion constraint "poly_bricks_no_overlap"`). Données de test nettoyées.
- **Prototype v0 codé** (`index.html`, fichier unique) : création/rejointe de salle par code, palette de briques par instrument (frappes types + éditeur avec décalage de hauteur), grille commune synchronisée en temps réel, blocage des conflits (pré-vérification locale + erreur serveur remontée), phases composition/jeu, lecture Web Audio (ordonnanceur à anticipation, curseur, clignotement des briques jouées), tempo partagé, pattern individuel (texte copiable + écoute solo), reprise d'identité par `localStorage`.
- Fichiers hébergés provisoirement dans `auberge-espagnole/partage-du-spectre/` en attendant le dépôt dédié.
- **À faire** : tester à plusieurs en conditions réelles ; créer le dépôt dédié + GitHub Pages ; renseigner les clés Supabase ; affiner les timbres et les décalages de position (*avant*/*après*) à l'oreille ; envisager un métronome d'appui pendant la lecture.

### 2026-07-08 (1) — Révision initiale : spécification et schéma
- Spécification arrêtée en conversation : grille temps × spectre, 8 bandes, briques d'épaisseur 1–3, blocage strict, métrique librement configurable, briques définies par le joueur (frappe + bande + épaisseur + variante par décalage de hauteur).
- Schéma Postgres conçu (3 tables + contrainte d'exclusion gist pour le blocage serveur). Extension `btree_gist` installée sur le projet Supabase.
- ~~**Blocage en cours** : les outils `apply_migration` et `execute_sql` du connecteur Supabase échouent avec une erreur opaque sur les `CREATE TABLE` (un `CREATE EXTENSION` + `SELECT` passe). Cause non identifiée ; contournement à trouver (SQL Editor manuel ou nouvel essai).~~ *Résolu le jour même, voir révision 2.*
- Inventaire des dépôts réutilisables effectué ; moteur audio du `metronome` extrait et validé comme base sonore.
- Application non codée à ce stade.

## Licence

Aucun fichier LICENSE à ce stade : code « tous droits réservés » par défaut. *(MIT recommandée si le dépôt est destiné au partage.)*
