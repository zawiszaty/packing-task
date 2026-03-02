# Project Context for Agents

## What is this project
- PHP microservice for packaging calculation in checkout/cart flow.
- Goal: return the smallest single configured box for a list of products.
- Primary calculation provider: 3DBinPacking API.
- Fallback: local manual calculation when provider is unavailable.

## How to run (Docker only)
1. Initialize env file:
   - `printf "UID=$(id -u)\nGID=$(id -g)" > .env`
2. Start containers:
   - `docker-compose up -d`
3. Enter app container:
   - `docker-compose run shipmonk-packing-app bash`
4. Inside container, install and prepare DB:
   - `composer install`
   - `bin/doctrine orm:schema-tool:create`
   - `bin/doctrine dbal:run-sql "$(cat data/packaging-data.sql)"`
5. Run sample request:
   - `php run.php "$(cat sample.json)"`

## Key files (read first)
- [Task Description](/home/zawiszaty/packing-task-stub/task.md)
- [Requirements](/home/zawiszaty/packing-task-stub/requirements.md)
- [High Level Design](/home/zawiszaty/packing-task-stub/hld.md)
- [Event Storming Mermaid](/home/zawiszaty/packing-task-stub/docs/ES.mmd)
- [Run Entrypoint](/home/zawiszaty/packing-task-stub/run.php)
- [Application Entrypoint](/home/zawiszaty/packing-task-stub/src/Application.php)
- [Existing Entity](/home/zawiszaty/packing-task-stub/src/Entity/Packaging.php)
- [Bootstrap / Doctrine](/home/zawiszaty/packing-task-stub/src/bootstrap.php)
- [Sample Input](/home/zawiszaty/packing-task-stub/sample.json)

## Working rules
- Use layered architecture and DDD-style entities/value objects.
- Keep response behavior-oriented (`BOX_RETURNED`, `NO_BOX_RETURNED`, `REQUEST_REJECTED`).
- Use order-insensitive input hash for repeated requests.
- Keep cache local (no Redis or external cache).
- Run static checks/tests through Docker container.
