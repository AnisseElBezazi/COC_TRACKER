# Clash League Tracker

Suivi des ligues classées de **Clash of Clans** : on entre un tag de joueur, l'application importe son groupe de la
semaine et affiche la **méta des armées** (archétypes de troupes, de héros et d'équipements, avec les images), le
**classement prévu** en fin de semaine avec les zones de promotion et de relégation propres à chaque ligue, et les
**meilleurs attaquants et défenseurs**.

Projet de **Anisse El Bezazi** et **Quentin Deglas**.
Dépôt : <https://github.com/AnisseElBezazi/COC_TRACKER>

## Prérequis

- PHP 8.4 ou plus (extensions `pdo_sqlite`, `intl`, `mbstring`)
- [Composer](https://getcomposer.org)
- [Symfony CLI](https://symfony.com/download) (facultatif : `php -S` suffit)

## Lancer le projet

```bash
# 1. Installer les dépendances
composer install

# 2. Configurer la base : créer .env.local (ignoré par git) avec une base SQLite locale
echo 'DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"' > .env.local

# 3. Créer les tables
php bin/console doctrine:migrations:migrate --no-interaction

# 4. Démarrer le serveur
symfony serve -d            # ou : php -S 127.0.0.1:8000 -t public
```

Ouvre <http://127.0.0.1:8000>, entre un tag (le champ est prérempli avec `#82U22QLL`), puis choisis une semaine.
La première fois, l'application appelle l'API (6 appels), puis analyse les combats des **20 premiers** du groupe ;
un bouton propose d'analyser les autres joueurs. La documentation de l'API REST est sur `/api/docs`.

> `.env` contient une base PostgreSQL par défaut ; les migrations du projet sont écrites pour **SQLite**,
> d'où l'étape 2.

## Si le serveur de l'API est en panne : mode hors ligne

Des réponses réelles sont enregistrées dans [`fixtures/api/`](fixtures/api/LISEZ-MOI.md). Pour les utiliser, ajoute
dans `.env.local` :

```
COC_API_URL=fixtures/api
```

puis relance l'import (`php bin/console app:import-joueur "#82U22QLL" --membres`). Retire la ligne pour revenir à l'API.

## Commandes utiles

| Commande | Rôle |
|---|---|
| `php bin/console app:import-joueur "#82U22QLL" --membres` | importe un joueur, ses groupes et les combats de ses membres |
| `php bin/console app:actualiser-groupes` | rafraîchit les groupes de la semaine en cours (seuls les 20 premiers, et seulement ceux qui ont attaqué) |
| `php bin/console messenger:consume scheduler_default` | lance le planificateur : `app:actualiser-groupes` toutes les 30 minutes |
| `php bin/phpunit` | lance les tests (64 tests) |
| `php tools/build-css.php --sans-clash` | reconstruit `public/css/site.css` à partir de `assets/css/` (thème bleu ; sans l'option : thème marron) |

À défaut du planificateur Symfony, un cron système fait la même chose :
`*/30 * * * * cd /chemin/du/projet && php bin/console app:actualiser-groupes --no-debug`.

## Schéma de données : l'Excel

Le fichier [`mapping-donnees.xlsx`](mapping-donnees.xlsx) (à la racine) décrit le modèle de données. Il doit rester
le reflet du code ; ses onglets :

| Onglet | Contenu |
|---|---|
| **Projet** | l'équipe, la source de données, la commande d'import, le dépôt |
| **Entités** | les 9 entités, leur clé naturelle, leurs groupes de lecture et d'écriture |
| **Mapping des champs** | chaque champ de l'API → entité, propriété, type Doctrine, contraintes, groupes |
| **Relations** | les relations entre entités, côté propriétaire et côté inverse (objet embarqué ou IRI) |
| **Règles ETL** | ce que fait l'import, étape par étape (normaliser, filtrer, dédoublonner, mettre en cache…) |
| **Auto-contrôle** | vérifie que le fichier est complet |

## Source des données

L'API officielle de Clash of Clans, via un proxy sans clé : `https://api.quentindeglas.com/v1`
(profil, combats, historique des ligues, groupes). Documentation : <https://developer.clashofclans.com/#/documentation>.
Les réponses sont gardées en cache quelques minutes (`config/packages/cache.yaml`).

## Organisation du code

| Dossier | Contenu |
|---|---|
| `src/Entity` | le modèle de données (Doctrine + API Platform) |
| `src/Service` | import (`ImportService`, `CocApi`), prédiction, statistiques, décodage des armées |
| `src/Controller`, `templates/` | les pages : accueil, ligues, groupe (méta, projection, top joueurs), joueur |
| `src/Command` | `app:import-joueur`, `app:actualiser-groupes` |
| `assets/css` | les feuilles de style, assemblées dans `public/css/site.css` |
| `data/` | catalogue des troupes, règles de promotion par ligue, index de la planche d'icônes |
| `fixtures/api` | réponses enregistrées pour le mode hors ligne |
| `tests/` | tests unitaires et fonctionnels |
