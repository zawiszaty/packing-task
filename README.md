# Packing Task Stub

PHP microservice for package size calculation (smallest matching single box for given products).

## Quick Links
- [Solution](SOLUTION.md)
- [High Level Design (HLD)](hld.md)
- [Event Storming (Mermaid)](docs/ES.mmd)
- [Event Storming (PNG)](docs/ES.png)
- [Requirements](requirements.md)
- [Task Description](task.md)
- [API Spec](docs/openapi.yaml)
- [Run Entrypoint](run.php)
- [Application Bootstrap](src/bootstrap.php)
- [Application Entrypoint](src/Application.php)
- [Sample Input](sample.json)

## Init
- `cp .env.example .env && sed -i "s/^UID=.*/UID=$(id -u)/; s/^GID=.*/GID=$(id -g)/" .env`
- set `THREEDBP_USERNAME` and `THREEDBP_API_KEY` in `.env` for real provider calls
- `docker-compose up -d`
- `docker-compose run shipmonk-packing-app bash`
- `composer install && bin/doctrine orm:schema-tool:create && bin/doctrine dbal:run-sql "$(cat data/packaging-data.sql)"`

## Run
- `php run.php "$(cat sample.json)"`

## Adminer
- Open `http://localhost:8080/?server=mysql&username=root&db=packing`
- Password: `secret`
