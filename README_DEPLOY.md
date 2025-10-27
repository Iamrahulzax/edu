````markdown
name=README_DEPLOY.md
```markdown
# Run the project locally with Docker

Prerequisites:
- Docker and docker-compose installed

1. Copy `.env.example` -> `.env` and edit DB credentials if you want.
2. Make sure `ecoedu_db.sql` (the SQL dump you provided) is in the project root.
   - The docker-compose `db` service mounts `./ecoedu_db.sql` into MariaDB's init folder so the DB is created on first start.
3. Build and start:
   ```
docker-compose up --build
   ```
4. Wait until both services are ready. Visit:
   - http://localhost:8080

Notes
- If your PHP app expects a `public/` document root, update the Dockerfile or move files accordingly. The Dockerfile above assumes the app's entry (index.php) is reachable at `/var/www/html`.
- To connect from your local machine with a DB client use:
  - Host: 127.0.0.1, Port: 3306, User: `ecoedu` (or `root`), Password: as set in docker-compose.
- If you use Composer and want automatic `.env` loading, run:
  ```
composer require vlucas/phpdotenv
  ```
  and keep `.env` in the repo root (do NOT commit secrets).
```
``