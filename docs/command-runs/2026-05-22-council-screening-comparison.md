# 2026-05-22 Council Screening Comparison

Purpose: test `nexus:screen --mode=council` with real OpenRouter calls on a bounded TomatoMAP screening set, then compare the result against the earlier single-model smoke and expected manual scientific screening.

## Source Notes

The core screening client uses OpenRouter structured outputs through `response_format` JSON schema. OpenRouter documents structured outputs as model-dependent, and its Models API exposes `supported_parameters`, including `structured_outputs`.

Relevant references:

- https://openrouter.ai/docs/features/structured-outputs
- https://openrouter.ai/docs/overview/models

## Test Set

| Work id | Title | Expected scientific screen |
| --- | --- | --- |
| `c17bc19e-0fa0-453f-bb1e-5879b7723148` | `39. Semi-supervised semantic segmentation for grape bunch identification in natural images` | Include for broad crop/fruit label-efficiency methods; needs review if the corpus is restricted to tomato instance segmentation only. |
| `82e023c2-8e6a-4d2a-9cf3-712556fe8541` | `Review of deep learning: concepts, CNN architectures, challenges, applications, future directions` | Exclude. Broad review, not a primary crop/plant segmentation or label-efficiency study. |
| `65430cd4-b82c-4575-ace9-9f1e294199fc` | `Bidirectional Copy-Paste for Semi-Supervised Medical Image Segmentation` | Exclude. Medical imaging is explicitly out of scope. |

Criteria used:

```text
Include:
- crop image segmentation
- tomato, fruit, or plant visual recognition
- label-efficient, semi-supervised, weakly supervised, active learning, or low-annotation visual recognition

Exclude:
- medical imaging
- remote sensing only
- review or survey without original experimental method
- general computer vision without crop, plant, or agricultural relevance
```

## Earlier Single-Model Baseline

Run id: `e1bd4964376618d65f31c79ce478b169`

Model: `openai/gpt-4.1-mini-2025-04-14`

Result for `c17bc19e-0fa0-453f-bb1e-5879b7723148`:

- Decision: `include`
- Confidence: `0.9`
- Reason: matched crop image segmentation and semi-supervised/label-efficient visual recognition.

## Council Attempt With Anthropic Default

Command:

```powershell
$env:NEXUS_LLM_SCREENING_ENABLED = 'true'
php artisan config:clear

php artisan nexus:screen `
  --project=tomatomap_label_efficiency `
  --mode=council `
  --work-ids=c17bc19e-0fa0-453f-bb1e-5879b7723148,82e023c2-8e6a-4d2a-9cf3-712556fe8541,65430cd4-b82c-4575-ace9-9f1e294199fc `
  --include="crop image segmentation" `
  --include="tomato, fruit, or plant visual recognition" `
  --include="label-efficient, semi-supervised, weakly supervised, active learning, or low-annotation visual recognition" `
  --exclude="medical imaging" `
  --exclude="remote sensing only" `
  --exclude="review or survey without original experimental method" `
  --exclude="general computer vision without crop, plant, or agricultural relevance" `
  --council-models="openai/gpt-4.1-mini,google/gemini-2.5-flash,anthropic/claude-3.5-haiku" `
  --max=3 `
  --name="TomatoMAP council smoke 20260522_20260522_113527"
```

Run id: `92c08e46aee66600e0f9bc8466c177ed`

CLI result:

```text
Run: 92c08e46aee66600e0f9bc8466c177ed | Total: 3 | Include: 1 | Needs review: 0 | Exclude: 2 | Failed: 0
```

Finding:

- OpenAI and Gemini produced valid votes.
- `anthropic/claude-3.5-haiku` failed on every paper with OpenRouter routing error: no endpoint could handle the requested parameters.
- Core preserved the failed model attempt in `screening_votes` and still aggregated the available votes.
- This is good failure handling, but it is not a good default council configuration.

## Council Attempt With Structured-Output-Compatible Mistral

OpenRouter model metadata was checked and `mistralai/mistral-small-2603` reported `structured_outputs` support. The council was rerun with OpenAI, Gemini, and Mistral.

Command:

```powershell
$env:NEXUS_LLM_SCREENING_ENABLED = 'true'
php artisan config:clear

php artisan nexus:screen `
  --project=tomatomap_label_efficiency `
  --mode=council `
  --work-ids=c17bc19e-0fa0-453f-bb1e-5879b7723148,82e023c2-8e6a-4d2a-9cf3-712556fe8541,65430cd4-b82c-4575-ace9-9f1e294199fc `
  --include="crop image segmentation" `
  --include="tomato, fruit, or plant visual recognition" `
  --include="label-efficient, semi-supervised, weakly supervised, active learning, or low-annotation visual recognition" `
  --exclude="medical imaging" `
  --exclude="remote sensing only" `
  --exclude="review or survey without original experimental method" `
  --exclude="general computer vision without crop, plant, or agricultural relevance" `
  --council-models="openai/gpt-4.1-mini,google/gemini-2.5-flash,mistralai/mistral-small-2603" `
  --max=3 `
  --name="TomatoMAP council smoke structured 20260522_20260522_113705"
```

Run id: `c3de2749c82c3f79592dadc0084e0854`

CLI result:

```text
Run: c3de2749c82c3f79592dadc0084e0854 | Total: 3 | Include: 0 | Needs review: 1 | Exclude: 2 | Failed: 0
```

Persisted decisions:

| Work id | Aggregate decision | Confidence | Vote pattern | Interpretation |
| --- | --- | ---: | --- | --- |
| `c17bc19e-0fa0-453f-bb1e-5879b7723148` | `needs_review` | `0.695` | OpenAI include, Gemini include, Mistral exclude | Scientifically useful disagreement. The paper is crop/fruit semi-supervised segmentation, but not tomato and not instance segmentation. Manual review is appropriate if the final corpus boundary is strict. |
| `82e023c2-8e6a-4d2a-9cf3-712556fe8541` | `exclude` | `0.9767` | 3 of 3 exclude | Matches expected manual screen. |
| `65430cd4-b82c-4575-ace9-9f1e294199fc` | `exclude` | `1.0` | 3 of 3 exclude | Matches expected manual screen. |

Audit settings:

- `store_prompt`: `false`
- `store_raw_response`: `false`
- persisted prompt length: `0`
- persisted raw response length: `0`

## Findings

1. Single-model screening is useful for fast triage, but it can hide boundary ambiguity.
2. Council mode correctly surfaced the grape-vs-tomato scope question as `needs_review`.
3. The council aggregator handled model failure without failing the whole run, but the default model list should avoid models that cannot route structured-output requests.
4. `mistralai/mistral-small-2603` worked as a third structured-output-compatible family in this smoke test.
5. Criteria wording matters. If grape/other-crop label-efficient segmentation is intended as transferable method evidence, use wording such as `crop, fruit, or plant visual recognition including tomato-adjacent agricultural segmentation`. If the included corpus must be TomatoMAP-specific instance segmentation only, keep the current stricter wording and expect such papers to land in `needs_review`.

## Recommended Next Test

Run a 20-50 paper council batch with this model set after manually defining the final corpus boundary:

```powershell
php artisan nexus:screen `
  --project=tomatomap_label_efficiency `
  --mode=council `
  --council-models="openai/gpt-4.1-mini,google/gemini-2.5-flash,mistralai/mistral-small-2603" `
  --include="crop, fruit, or plant image segmentation" `
  --include="tomato or tomato-adjacent agricultural visual recognition" `
  --include="label-efficient, semi-supervised, weakly supervised, active learning, or low-annotation visual recognition" `
  --exclude="medical imaging" `
  --exclude="remote sensing only" `
  --exclude="review or survey without original experimental method" `
  --max=25 `
  --name="TomatoMAP council calibration batch"
```
