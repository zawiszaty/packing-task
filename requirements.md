# Packaging Service Requirements

## Scope
- Build a microservice that receives a list of products and returns the single smallest usable box.
- Use 3DBinPacking API ("Pack a Shipment") for primary packing calculation.
- Cache/store previous calculations to avoid repeated external API calls for identical input.
- Use local fallback packing logic when external API is unavailable.

## Functional Requirements
1. Accept request body as JSON with products list: `[{ "id", "width", "height", "length", "weight" }]`.
2. Validate request:
   - Required fields must exist for each product.
   - Dimensions and weight must be numeric and greater than `0`.
   - Product list must not be empty.
   - Reject malformed JSON.
   - Use dedicated presentation `Input DTO` contract for request parsing/validation.
3. Load available boxes from backend configuration (database-backed list from `packaging` table).
4. Build deterministic hash key from product identifiers only (`id`) with order-insensitive comparison and item multiplicity support.
5. Check stored previous calculations by hash:
   - If found and latest record is non-refreshable, return cached decision without new provider call.
   - If found and latest record is refreshable (manual source), start refresh attempt and still return cached decision for current request.
6. When no cached result:
   - Call 3DBinPacking pack endpoint (`POST /packer/packIntoMany`) with account API key.
   - Allow item rotation in any direction.
   - Request best packing for a single shipment attempt.
7. Parse external API response:
   - Determine whether all products fit into exactly one available backend box.
   - If external result uses multiple bins or indicates unpacked items, return "not packable to single box".
8. Select the smallest usable box among candidates that can hold all products and weight.
9. Persist calculation result (request hash + selected outcome + normalized provider/manual result + status).
10. Return response:
   - Use behavior-oriented outcome codes for successful processing paths.
   - Success: selected box dimensions/maxWeight (and optionally identifier).
   - Domain failure: explicit reason (`NO_BOX_RETURNED` with reason/message details).
   - Validation failure: HTTP `422` with `errors[]` payload (`code`, `title`, `detail`), not a domain outcome.
   - Use dedicated presentation `Output DTO` contract for response serialization.
11. Fallback behavior:
   - If external API is down or times out, run local simple packing heuristic.
   - Heuristic must still enforce dimensions and total weight constraints.
   - Return fallback result in the same response contract as provider result.
   - Persist fallback result as a new calculation row with `providerSource = manual`.
12. Refresh behavior for manual results:
   - `PackingCalculation` entity should expose behavior like `requiresRefresh(): bool` based on `providerSource` (for now: `manual` -> `true`, external providers -> `false`).
   - On later matching requests, if latest cached record is refreshable, attempt recalculation with policy registry (provider when available, manual when provider path is unavailable/fails).
   - If latest record is already from `provider_3dbinpacking`, refresh is not required and no provider re-call is triggered.
   - Refresh result should be stored as another new row (do not overwrite previous row), preserving history and still serving cached manual result for the request that triggered refresh.
13. Support operational edge cases typical for cart usage:
   - duplicate products,
   - very small/very large numeric values,
   - floating-point inputs,
   - many items in one request.

## Non-Functional Requirements
1. Reliability:
   - Service should keep responding when 3DBinPacking is unavailable (fallback path).
   - No unhandled exceptions leaking to clients.
2. Performance:
   - Cache-first behavior for duplicate requests.
   - Target response time for cache hit: <= 100 ms in local environment.
   - External-call path should use Guzzle default timeout for now.
3. Scalability:
   - Hash-based lookup must be indexed and efficient for growing history.
   - Avoid N+1 database queries when loading boxes and calculations.
4. Resilience:
   - No automatic retries for provider calls.
   - Circuit breaker is mandatory and enabled by default for external provider calls.
   - Respect 3DBinPacking rate limits by minimizing repeated calls and optionally adding backoff.
5. Data integrity:
   - Persist either full successful result or explicit failure outcome atomically.
   - Consistent unit handling: kilograms (`kg`) for weight; dimensions must use one canonical unit across request, boxes, and provider mapping.
6. Security:
   - API key must be injected via environment/config, never hardcoded.
   - Do not expose external API key or raw sensitive data in responses/logs.
7. Observability:
   - Structured logs with correlation/request hash, cache hit/miss, fallback usage, provider latency.
   - Metrics counters: `cache_hit`, `cache_miss`, `fallback_used`, `provider_error`, `validation_error` (planned, not implemented in current code).
8. Testability:
   - Unit tests for domain logic and fallback algorithm.
   - Integration tests for Doctrine repositories and API client adapter.
   - Contract tests for request/response schema and error mapping.
9. Maintainability:
   - Code should stay modular and easy to evolve for additional providers.
   - API contract must be documented in OpenAPI/Swagger format and versioned in repository.

## API Contract Artifact
- Swagger/OpenAPI specification is maintained in [docs/openapi.yaml](/home/zawiszaty/packing-task-stub/docs/openapi.yaml).
- Presentation contract is split into `Input DTO` and `Output DTO`.

## Decisions Confirmed
- Request hash is order-insensitive, so `[item1, item2]` and `[item2, item1]` use the same cached key.
- Request hash identity is based on product `id` values (plus multiplicity), not product dimensions/weight.
