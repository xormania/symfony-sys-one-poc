# Upstream evidence and contribution boundary

Source review: 2026-09-27. The application locks Symfony AI 0.14.0; inspect `composer.lock` for exact package references.

## Existing upstream work

- [Symfony AI #2546](https://github.com/symfony/ai/issues/2546): maintainers prefer bridge-local value objects until provider commonality is demonstrated.
- [Symfony AI #2549](https://github.com/symfony/ai/pull/2549): TypeSafe bridge merged September 21 and released in 0.14.0.
- [Earlier generic-classification prototype](https://github.com/wachterjohannes/symfony-ai/pull/96): closed without merging; useful design history, not an accepted API.
- [Blog classification demo #2564](https://github.com/symfony/ai/issues/2564): existing application example; this PoC focuses on provider substitution.

## What this implementation establishes

| Observation | Evidence and limit |
| --- | --- |
| Both configured PHP integrations can use identical evaluation payloads | Kernel integration test retains the real Bundle, client, normalizer, converter, and response types; only HTTP is mocked. Live server equivalence remains unverified. |
| Custom endpoint support exists below the Bundle | TypeSafe `Factory::createPlatform()` accepts `baseUrl`. |
| Normal TypeSafe Bundle configuration lacks endpoint and named-instance options | The 0.14 configuration provides `api_key` and `http_client`, and creates `ai.platform.typesafe`. CLM is explicitly wired in this PoC. A scoped client's `base_uri` alone does not override the absolute URL constructed by the bridge. |
| The open model catalog is sufficient for a compatible model name | Registering `clm-latest` with the bridge's `Jev` class works through its existing client/converter. This is transport reuse with a provider-specific name. |
| Ranking already has common Symfony result types | This PoC uses `RerankingResult` / `RerankingEntry`. It proposes no duplicate ranking abstraction. |
| Successful decoding does not establish a useful model | Contract and behavioral outcomes are separate in every report. |

Sources: [factory](https://github.com/symfony/ai/blob/v0.14.0/src/platform/src/Bridge/TypeSafe/Factory.php), [Bundle configuration](https://github.com/symfony/ai/blob/v0.14.0/src/ai-bundle/config/platform/typesafe.php), [model catalog](https://github.com/symfony/ai/blob/v0.14.0/src/platform/src/Bridge/TypeSafe/ModelCatalog.php), [reranking result](https://github.com/symfony/ai/blob/v0.14.0/src/platform/src/Result/RerankingResult.php).

## Smallest candidate contribution

Expose TypeSafe `base_url` in the AI Bundle and verify that the configured value reaches the factory. A working CLM request would provide a concrete motivating example. Named instances and catalog ergonomics are related follow-ups, not prerequisites for a first patch.

Do not present the mock integration tests as a measured two-model result. Before proposing generic typed-decision types, attach actual provider reports and identify code or semantics that cannot already be shared using the existing bridge. If configuration and documentation solve the problem, that is a valid result.

## Model findings to reproduce

- [CLM #3](https://github.com/Contrastive-LM/CLM/issues/3): reported state-insensitive Score results. The paired typed fixtures directly exercise this concern.
- [CLM #15](https://github.com/Contrastive-LM/CLM/issues/15): reported README reproduction differences and state-embedding issues. These are external reports, not findings produced here.
- [CLM PR #6](https://github.com/Contrastive-LM/CLM/pull/6): proposed correction for truncation direction. The initial fixtures are intentionally short; they do not validate long-state handling.
- [CLM engine at the inspected revision](https://github.com/Contrastive-LM/CLM/blob/bb42c6c5bf914fd449bed2f6ca65be80602cb1f7/src/clm/engine.py): `rank()` builds a Choice question and sorts its distribution. The separate native endpoint is not required for the first experiment.

Nothing in this repository is an upstream proposal already submitted or approved.
