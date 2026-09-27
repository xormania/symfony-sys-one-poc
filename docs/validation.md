# Validation record

Recorded 2026-09-27. This record distinguishes PHP integration evidence from model evidence.

## Verified locally

`composer verify` completed with exit code 0 on both PHP **8.4.25** and **8.5.11**, using the committed lock file:

| Check | Result |
| --- | --- |
| Composer manifest and lock validation | Passed in strict mode |
| Symfony compiled container | Passed `lint:container --env=test` |
| PHPUnit 13.3.5 | 31 tests, 94 assertions passed on each PHP version |
| PHPStan 2.2.16 | Maximum level; no errors or baseline |
| PHP CS Fixer 3.95.27 | Symfony rules and strict types; no changes required |
| Offline fixture listing | Eight fixtures listed without contacting a provider |

The locked framework is Symfony 8.1.7 and the AI packages are 0.14.0. CI repeats the verification command on PHP 8.4 and 8.5 with native `intl` and `curl` extensions. Consult the PR's CI checks for remote results.

The local static PHP binaries cannot load PHPStan's optional Turbo extension; PHPStan printed an extension warning, continued, and completed analysis successfully. The local verification did not require a model server, credentials, or a GPU.

The integration tests boot the actual Symfony kernel and use the AI Bundle's Jev service plus the explicitly wired CLM service. HTTP responses are synthetic, independently authored protocol samples. They exercise the real bridge serialization, normalization, conversion, typed answers, and ranking results. They do **not** measure inference quality or real server compatibility.

Failure tests distinguish behavioral errors from protocol, transport, and provider failures. They verify that one failed provider does not prevent the other from running, and that reports exclude raw error bodies. Command tests also check provider order, stable candidate IDs, JSON/file equivalence, and preservation of literal formatting characters in provenance labels.

## Live evidence

| Provider | Observation | Conclusion |
| --- | --- | --- |
| Jev | No TypeSafe key was available in the execution environment. | No live Jev request or quality measurement was made. |
| CLM | A request to the default local endpoint failed to connect. The command recorded a transport error and returned exit code 2. | Error reporting was exercised; no CLM inference was performed. |

No accuracy, throughput, cost, calibration, or model-equivalence result is claimed. The CLM GPU setup recipe is source-based and has not been deployed here.

## Next experiment

1. Supply a TypeSafe key and start a CLM deployment, recording the actual encoder and projection-head revisions described in [the runtime notes](clm-runtime.md).
2. Run the same eight fixtures against both providers, saving a JSON report. Repeat measurements only with cache conditions recorded; the command does not reset server caches.
3. Inspect contract failures before behavioral outcomes. Preserve failures in the report, especially the paired Score results and the reversed candidate order.
4. Decide from that evidence whether the next useful change belongs in model/runtime behavior, Bundle configuration, or documentation. A new generic decision API requires additional evidence that the existing types cannot express a shared need.

The provisional numeric expectations are smoke criteria, not calibrated production thresholds. Do not adjust them merely to turn a failing result green.
