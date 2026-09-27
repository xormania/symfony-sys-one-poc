# Symfony System One PoC

Can one Symfony application use hosted Jev and local CLM for the same bounded decisions, and what changes does Symfony actually need to make that convenient?

This is a small experiment built on **Symfony 8.1**, **Symfony AI 0.14**, and the existing **TypeSafe bridge**. It uses the AI Bundle for Jev and the same unmodified bridge with a different endpoint and model catalog for CLM. The application reuses Symfony's typed questions, typed answers, and reranking results.

**Status:** the application and offline protocol tests are implemented. Live Jev/CLM model equivalence and usefulness are **unverified**. Passing mocked HTTP tests proves the PHP integration, not either model's quality. See [validation](docs/validation.md).

## Run

Requires PHP **8.4+**, Composer 2, and the extensions checked by Composer. PHP 8.5 is the recommended runtime. Install `intl` for Symfony's native internationalization support; `curl` is recommended for HttpClient. CI verifies PHP 8.4 and 8.5.

```bash
composer install
php bin/console app:system-one:run --list
composer verify
```

Set credentials and the local server in an untracked `.env.local`:

```dotenv
TYPESAFE_API_KEY=your-typesafe-key
JEV_MODEL=jev-1.13.0
CLM_BASE_URL=http://127.0.0.1:8700
CLM_API_KEY=
CLM_MODEL=clm-latest
```

`CLM_BASE_URL` is the server root. The bridge appends `/v1/systemone`. `CLM_API_KEY` may be empty when the local server does not require authentication. [CLM setup and reproduction notes](docs/clm-runtime.md) explain the separate encoder and API processes.

```bash
# Both providers, eight identical fixtures each
php bin/console app:system-one:run --output=var/reports/comparison.json

# One provider or a targeted reproduction
php bin/console app:system-one:run --provider=clm --case=typed.calm --case=typed.angry

# Repeat measurements and preserve the operator-declared model/runtime provenance
php bin/console app:system-one:run --repeat=3 --format=json \
  --runtime-label='CLM commit=...; head sha256=...; Qwen revision=...; vLLM=...; GPU=...' \
  --output=var/reports/repeated.json
```

There is no automatic fallback, retry, synthetic provider, or silent skip. Missing credentials and unavailable servers are reported as errors. The command continues collecting results from the other selected provider. An explicitly selected single provider is a single-provider run; it does not establish interchangeability.

| Exit code | Meaning |
| --- | --- |
| `0` | Every selected evaluation satisfied the response contract and fixture expectations. |
| `1` | Responses were compatible, but one or more behavioral expectations failed. |
| `2` | Configuration, connection, provider, response-contract, or argument failure. Behavioral conclusions for failed calls are unavailable. |

Reports are JSON, written atomically when `--output` is supplied. They preserve model IDs, full distributions, confidence, stable candidate IDs, request/suite/lock hashes, dependency references, per-call latency, provider-reported usage, failures, and repeat indices. Raw provider exception messages and credentials are excluded. Review the operator-provided runtime label before sharing a report.

## What the experiment tests

| Fixture | Expected useful behavior |
| --- | --- |
| Five capability cases | Select Messenger, a voter, Validator, Mercure, or `none` from six fixed descriptions. |
| Reversed candidate order | Preserve the background-worker selection after reordering candidates. |
| Satisfied and angry customers | Change `Noul`, `Choice`, and `Score` results when the state changes. The short Score rubric deliberately exposes the concern reported in CLM issue #3. |

The expectations are hand-authored smoke criteria, including provisional numeric ranges. They are kept outside the provider payload. Eight cases cannot estimate general accuracy, calibration, or production suitability. The `none` case supplies an explicit alternative; it does not prove the model can reliably detect every missing candidate.

Ranking is the complete Choice distribution sorted into Symfony `RerankingResult` entries, with indices mapped back to the original candidate IDs. At the inspected CLM revision, its native `/v1/rank` implementation also delegates to Choice evaluation. We therefore test the shared primitive first; native endpoint parity is not claimed.

## Symfony boundaries

- `config/packages/ai.php`: standard AI Bundle configuration for Jev.
- `config/services.php`: CLM's model catalog and TypeSafe factory configuration. This is the small explicit-wiring exception while the Bundle lacks `base_url` and named TypeSafe instances.
- `src/SystemOne/`: provider selection, invocation, response-contract checks, and reuse of Symfony result types.
- `src/Benchmark/`: the fixture corpus, independent behavioral checks, and evidence report.
- `src/Command/`: one attribute-based command; services are autowired.

`Evaluator` is an experimental application service, not a proposed Symfony Platform interface. The existing bridge-specific types are sufficient for this implementation. The CLM catalog currently uses the bridge's `Jev` model class because the bridge's client/converter select it; this naming coupling is documented evidence, not a claim about CLM's model architecture.

CUE and Agentscient are possible consumers of the capability. This repository contains neither their domain models nor recursive orchestration.

## Interpret the evidence

1. **Compatibility:** all question and candidate IDs survive; types, numeric ranges, distributions, score legends, and weighted scores are coherent.
2. **Behavior:** a compatible response meets the fixture's expected decision. High confidence never overrides a failed expectation.
3. **Timing:** measurements include deferred HTTP completion, parsing, and contract validation. Provider order alternates between repeats. Cache state is uncontrolled; iterations are not labeled cold/warm without external control.
4. **Usage:** Jev input tokens and CLM encoder cache-miss tokens are not the same accounting unit. No cross-provider cost or probability calibration is claimed.

The useful outcome may be a configuration/documentation improvement with no new abstraction. [Upstream findings](docs/upstream.md) distinguish verified code facts from proposed contributions.

## Development

```bash
composer test       # PHPUnit: actual kernel/Bundle and HTTP boundary, no model or API key required
composer analyse    # PHPStan at maximum level
composer style     # Symfony code style, including strict types
composer verify    # Locked dependencies, compiled container, tests, analysis, style
```

Tests use Symfony `MockHttpClient` at the transport boundary while retaining the actual TypeSafe bridge. They cover both configured providers, list/map score payloads, numeric identifiers, lazy response timing, malformed responses, authentication/rate-limit/server failures, behavioral failure, CLI reports, and credential-safe diagnostics. Their response samples are explicitly synthetic and are never a live comparison result.

The MIT license is in [LICENSE](LICENSE).
