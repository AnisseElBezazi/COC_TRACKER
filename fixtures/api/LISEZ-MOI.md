# Réponses enregistrées de l'API (mode hors ligne)

Ces fichiers sont de vraies réponses de `https://api.quentindeglas.com/v1`, enregistrées pour pouvoir tester
l'application **quand le serveur de l'API est en panne** : joueur `#82U22QLL`, son groupe de la semaine et de la
semaine précédente, la liste des ligues, et les combats des 20 premiers du groupe.

## Utilisation

Dans `.env.local` :

```
COC_API_URL=fixtures/api
```

Puis, comme d'habitude :

```
php bin/console app:import-joueur "#82U22QLL" --membres
symfony serve -d
```

Tape `#82U22QLL` sur la page d'accueil. Pour revenir à l'API réelle, supprime la ligne `COC_API_URL`.

## Nommage des fichiers

L'adresse appelée devient le nom du fichier : le `#` est retiré et chaque `/` devient `__`.

| Appel | Fichier |
|---|---|
| `players/%2382U22QLL` | `players__82U22QLL.json` |
| `players/%2382U22QLL/battlelog` | `players__82U22QLL__battlelog.json` |
| `players/%2382U22QLL/leaguehistory` | `players__82U22QLL__leaguehistory.json` |
| `leaguegroup/%23998U29Y/1795410000` | `leaguegroup__998U29Y__1795410000.json` |
| `leaguetiers` | `leaguetiers.json` |

Un fichier absent répond **404**, comme l'API pour un joueur inconnu : le joueur est simplement ignoré à l'import.
Les autres membres du groupe n'ont pas de fichier de combats, donc pas d'armée.

## Ajouter un autre joueur

Récupère les réponses avec `curl` (ou ton navigateur) et enregistre-les avec ce nommage, par exemple :

```
curl -s "https://api.quentindeglas.com/v1/players/%23XXXXXXX" > fixtures/api/players__XXXXXXX.json
```
