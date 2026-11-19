# Lecteur de base MaxMind DB

Copie (espace de noms `MaxMind\Db` renommé en `App\Support\MaxMindDb`) de `maxmind-db/reader` 1.x
(https://github.com/maxmind/MaxMind-DB-Reader-php), sous licence Apache 2.0 (voir `LICENSE`).
Copié dans l'application plutôt qu'installé avec Composer : le dossier `vendor/` du serveur est préparé à part,
et une dépendance de plus y serait absente. Il sert à lire la base de géolocalisation DB-IP (voir
`App\Services\IpGeolocation`) : il lit le format `.mmdb`, sans extension PHP particulière.
