# Packaging Service - High Level Design (HLD)

## Purpose
- Define the technical architecture for calculating the smallest single box for cart products.
- Keep behavior stable when external provider is unavailable.
- Support provider evolution (multiple providers in the future).

## Context
- Input: list of products `[{id, width, height, length, weight}]`.
- Internal data: configured boxes from `packaging` table.
- Primary provider: 3DBinPacking (`/packer/packIntoMany`).
- Output:
  - success decision with behavior-oriented outcome (`BOX_RETURNED` or `NO_BOX_RETURNED`),
  - or validation error response (`HTTP 422` with `errors[]`).

## Architecture
- Layered architecture:
  - `Presentation`: request parsing, validation mapping, response mapping.
  - `Application`: use-case orchestration and policy execution.
  - `Domain`: entities, value objects, policies, decision rules.
  - `Infrastructure`: Doctrine repositories, provider adapter (Guzzle), circuit breaker adapter.
- DDD approach:
  - Entities and value objects are primary modeling units.
  - No aggregate is required for this scope.
  - Domain remains independent from transport/DB/provider implementation details.

## Libraries and Technology Choices
- Preferred approach: Symfony components for framework-neutral building blocks.
- HTTP/provider integration:
  - `guzzlehttp/guzzle` for calling 3DBinPacking API.
- Persistence:
  - `doctrine/orm` for entities and repositories.
- Cache (local only, no Redis/external cache):
  - `symfony/cache` with local adapters (`FilesystemAdapter` for runtime, `ArrayAdapter` for tests).
- Validation and request handling (Symfony components):
  - `symfony/validator` for payload validation rules.
- Circuit breaker:
  - use external library (Symfony has no dedicated CB component), e.g. `ackintosh/ganesha`.
  - configure with local in-memory/storage mode (no Redis).

## Runtime and Execution Model
- All project commands must run through Docker containers (local and CI).
- Do not require host-installed PHP/composer tooling for build/test/analysis.
- Local cache remains container-local (`symfony/cache` local adapters only).

## Core Components
- Use case:
  - `FindBoxSize`
- Policies:
  - `PackingPolicyRegistry`
  - `RequiresRefreshPolicy`
- Policy implementations:
  - `ProviderPackingPolicy`
  - `ManualPackingPolicy`
- Domain rule:
  - `SmallestBoxSelector`
- Adapters:
  - `3DBinPackingClient` (Guzzle)
  - `PackagingRepository` (Doctrine)
  - `CalculationRepository` (Doctrine)
  - `CircuitBreaker`

## Presentation Contract
- Presentation layer uses explicit DTO split:
  - `Input DTO`: request parsing and validation.
  - `Output DTO`: behavior-oriented response serialization.
- Request/response mapping is implemented with Symfony `Serializer` + `Validator`.
- OpenAPI contract source of truth:
  - [docs/openapi.yaml](/home/zawiszaty/packing-task-stub/docs/openapi.yaml)

## Domain Model
- Entity: `PackagingBox`
  - `id`, `width`, `height`, `length`, `maxWeight`
- Entity: `PackingCalculation`
  - `id`, `inputHash`, `normalizedRequest`, `normalizedResult`, `selectedBoxId`, `providerSource`, `createdAt`, `refreshedAt`
  - behavior: `requiresRefresh(RequiresRefreshPolicy $policy)` to decide refreshability by policy rules (not hardcoded only by source)
- Value objects:
  - `Dimensions`
  - `Weight`
  - `ProductToPack`
  - `PackingRequest`

## Data/Read Model
- Read model: `LatestPackingResultProjection by NormalizedInputHash`
- Rules:
  - Input hash is order-insensitive.
  - Input hash is based on product `id` only (with multiplicity of repeated IDs).
  - Projection serves latest result quickly.
  - Persisted rows stay immutable; new outcomes append new rows.

## Main Flows
1. Request arrives -> validate payload.
2. Build normalized input hash.
3. Read projection by hash.
4. If projection result exists:
   - evaluate `RequiresRefreshPolicy`.
   - `NoRefreshNeeded` -> return response immediately.
   - `RefreshRequired`:
     - attempt refresh calculation via policy registry (provider when available, manual as failover),
     - persist refresh result row,
     - return current stored response for the triggering request.
5. If no projection result:
   - execute `PackingPolicyRegistry` flow:
     - provider path (3DBinPacking),
     - manual path (fallback).
6. Run `SmallestBoxSelector`.
7. Persist as new calculation row.
8. Return behavior-oriented response.

## Circuit Breaker and Fallback
- Circuit breaker is mandatory and enabled by default.
- No retries in provider client.
- If provider path fails, manual policy is used for normal calculation.
- For refresh flow, response is returned from existing stored row for the triggering request; refresh runs best-effort and failures are logged.

## External API Integration (3DBinPacking)
- Endpoint: `POST /packer/packIntoMany`
- Constraints:
  - Explicit units must be mapped consistently.
  - Provider errors/timeouts/rate limits must be handled and mapped.
  - Response with unpacked items or multi-bin when single box is required maps to `NO_BOX_RETURNED`.

## Response Contract (Behavior-Oriented)
```json
{
  "data": {
    "type": "packing-decision",
    "id": "requestHash",
    "attributes": {
      "outcome": "BOX_RETURNED",
      "reason": null,
      "message": null,
      "box": {
        "id": 3,
        "width": 30.0,
        "height": 20.0,
        "length": 40.0,
        "maxWeight": 10.0
      }
    }
  },
  "meta": {
    "source": "provider_3dbinpacking|manual",
    "requestHash": "..."
  }
}
```

```json
{
  "data": {
    "type": "packing-decision",
    "id": "requestHash",
    "attributes": {
      "outcome": "NO_BOX_RETURNED",
      "reason": "NO_SINGLE_BOX_AVAILABLE",
      "message": "Products cannot be packed into a single configured box.",
      "box": null
    }
  },
  "meta": {
    "source": "provider_3dbinpacking|manual",
    "requestHash": "..."
  }
}
```

```json
{
  "errors": [
    {
      "status": "422",
      "code": "VALIDATION_ERROR",
      "title": "Invalid request body",
      "detail": "id is required"
    }
  ]
}
```

## Quality Attributes
- Reliability: serve responses even during provider outage.
- Performance: projection-first path for repeated requests.
- Scalability: indexed hash lookup + append-only calculation rows.
- Maintainability: policy-based provider substitution.
- Observability: log source path (`provider_3dbinpacking`, `manual`) and provider/circuit state.

## Engineering Quality Toolchain
- Static analysis:
  - `phpstan` (strict level, baseline only if needed).
  - `psalm` (type-safety and taint-style checks where applicable).
- Code style:
  - `php-cs-fixer` as formatter and coding-standard enforcement.
- Tests:
  - `phpunit` for unit and integration tests.
  - `infection` for mutation testing of critical domain/application logic.
- Execution rule:
  - run all tools inside Docker app container (for example via `docker compose run/exec shipmonk-packing-app ...`).
- CI quality gate (recommended):
  - run `php-cs-fixer --dry-run`,
  - run `phpstan`,
  - run `psalm`,
  - run `phpunit`,
  - run `infection`.

## GitHub Actions CI (Required)
- Workflow trigger:
  - on `pull_request` to main development branch,
  - on `push` to protected branches.
- Required jobs:
  - `docker-build`: build application image and verify container startup.
  - `code-style`: run `php-cs-fixer --dry-run` inside Docker container.
  - `static-analysis`: run `phpstan` and `psalm` inside Docker container.
  - `tests`: run `phpunit` (unit + integration) inside Docker container.
  - `mutation-tests`: run `infection` inside Docker container (full run).
- Required status checks before merge:
  - all jobs above must pass.
  - branch must be up to date with base branch.
- Recommended CI setup details:
  - matrix for supported PHP versions (at least target runtime + one adjacent).
  - dependency caching for Composer to reduce runtime.
  - fail-fast disabled for matrix if you want full visibility of failures.
  - artifacts upload for test logs and Infection reports.
