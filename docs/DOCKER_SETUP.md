# Docker Setup

This is the quickest way to run the WPU Maintenance Request System locally.
Docker Compose starts PHP with the required `pdo_mysql` extension and MySQL
with the application schema and starter data.

## Requirements

- Docker Desktop, Docker Engine, or Docker Compose
- A browser

No local PHP, MySQL, XAMPP, or Composer installation is required.

## Start the application

From the project root, run:

```bash
docker compose up --build
```

The first run downloads the images, builds the PHP image, creates the database,
and imports `database/schema.sql` followed by `database/seed.sql`. Wait until
both services are running, then open:

<http://localhost:8000/>

The equivalent address is <http://127.0.0.1:8000/>.

## Default administrator account

- Email: `admin@wpu.ac.pg`
- Password: `Admin@12345`

Change this password after signing in.

## Stop and restart

Stop the services while keeping database data:

```bash
docker compose down
```

Start them again later:

```bash
docker compose up
```

MySQL data is stored in the `mysql_data` Docker volume. To remove the database
and force a completely fresh seed on the next start:

```bash
docker compose down -v
```

Do not use `-v` if you need to keep existing requests, users, or settings.

## Troubleshooting

Check service status and logs:

```bash
docker compose ps
docker compose logs app
docker compose logs db
```

If port `8000` or `3306` is already in use, stop the process using it or change
the host-side port before starting. For example, changing `8000:8000` to
`8080:8000` makes the browser address <http://localhost:8080/>.

The PHP application reads database settings from environment variables. Docker
Compose sets `DB_HOST=host.docker.internal` and maps that name to the Docker
host gateway, while a local XAMPP/PHP setup falls back to `127.0.0.1` in
`config/config.php`.
