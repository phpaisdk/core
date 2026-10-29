# Changelog

Notable changes to `aisdk/core`. Release notes are also published at https://github.com/phpaisdk/core/releases.

## v0.10.0 — 2026-10-29

### Agents

- `Agent` runs bounded tool loops with cumulative usage and a step budget. `Tool::needsApproval()` pauses a whole tool batch; resume with explicit allow/deny decisions, signed single-use tokens and your own atomic checkpoint store.
- Tool failures now go back to the model as error results by default. Use `throwOnToolError()` to abort instead, and `exposeToolErrors()` to include handler messages.
- Build tools from typed PHP callables with `Tool::fromCallable()`, and control loops with `onStepFinish()` and `stopWhen()`.

### New APIs

- `Failover::of(...)` tries models in order for text, images, speech, transcription, embeddings and video, with cooldowns and listeners.
- `PromptCache` / `->cache()` express prompt-cache hints; usage reports cache writes.
- `Generate::decision()` asks predicate, choice and score questions with native probabilities, confidence and explicit refusal.
- Portable file contracts: upload, retrieve, list, download and delete, with provider-bound stored references.
- `ResponseMetadata` exposes response id, resolved model and raw finish reason. Text-model calls can be observed without exposing conversation contents.

### Breaking changes

- Plain strings passed to `Content` factories no longer open files. Use `LocalFile::at()` or `LocalFile::within()`.
- `Stream::result()` requires a completed stream. Use `snapshot()` for partial output or `run()` to drain.
- Upgrade SDK packages together to matching `^0.10.0` versions.

### Security

- Credentials are redacted from transport errors and URLs.

## v0.9.0 — 2026-10-25

### Generation options

- `maxTokens`, `temperature` and `topP` stay unset unless you set them, so each provider's own defaults apply. `TextModelRequest::$maxTokens` and `$temperature` are now nullable; custom text model adapters must handle `null`. Provider packages that require a value (for example Anthropic) apply their own default.

### Reliability

- Add `RetryingHttpClient`, an opt-in PSR-18 decorator that retries connection errors, 408, 409, 429, 5xx and 529 with exponential backoff, jitter and `Retry-After`.
- Fix streaming finalization and usage aggregation across tool steps so `stream()` matches `run()`.
- Improve JSON Schema and JSON encoding portability.

### Security

- Require Guzzle 7.15.2 or newer, which fixes published security advisories.

### For agents

- Add `AGENTS.md` describing the architecture rules and conventions of the core package.

### Compatibility

Provider packages released for 0.8 keep using core 0.8. Update them together with their 0.9 releases.

## Earlier releases

See the [GitHub releases](https://github.com/phpaisdk/core/releases) for versions before 0.10.0.
