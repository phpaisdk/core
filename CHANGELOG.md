# Changelog

Notable changes to `aisdk/core`. Release notes are also published at https://github.com/phpaisdk/core/releases.

## v0.10.2 — 2026-10-29

- Download provider media with `RemoteFetch::bytes()`, bounded size and redirects, and per-hop checks blocking private networks by default.
- Failover retries the same model once for short `Retry-After` delays (up to 5 seconds); configure or disable with `retryAfterUpTo()`.
- Distinguish exhausted credits and quota with `InsufficientCreditsException` and automatically fail over to another model.
- Structured text output accepts a surrounding Markdown JSON fence, including streamed results.
- Stored file ids that could change the request route (`/`, `\`, `?`, `#`, `%`, whitespace) are rejected before any request.
- Provider errors wrapped in a one-element list (Google) are read correctly, so a bad API key is an `AuthenticationException`.

## Unreleased (v0.11.0)

- Inspect assistant tool-call announcements while approvals are pending; unknown calls settle as errors, and removed or no-longer-approvable tools cannot block or execute during resume. Approval applies equally to `Tool` subclasses.

- Wrap each agent generation step with immutable request middleware, validated short-circuits, `Stop` predicates, and resumable `StepBudget` limits.

- Observe agent and text tool-loop runs with a shared invocation id, step and tool events, timings, safe failures, and approval-resume continuity. Register isolated listeners with `onEvent()` and test with `RecordingEvents`.
- Observe successes and failures for image, speech, transcription, embedding, decision, and video model operations with `observe()`.

## v0.10.1 — 2026-10-29

- Error messages from providers are redacted for echoed credentials and capped in length; odd error bodies no longer raise PHP warnings.
- A bad API key reported as HTTP 400 (xAI, Google) is now an `AuthenticationException`; `Retry-After` HTTP dates are understood.
- Streams that return JSON or HTML, end without content, drop mid-body, or send an oversized event now fail with typed exceptions; a stream cut off before its finish event reports `FinishReason::Unknown`.
- In-stream errors map to `RateLimitException`, `OverloadedException` and friends via `HttpErrorNormalizer::fromStreamError()`.
- HTTP clients that throw non-PSR exceptions are wrapped in `APIConnectionException`.
- New `ApprovalException`; agent approval, decision and configuration errors are SDK exceptions instead of native PHP ones. A malformed approval submission keeps the approval pending.
- `maxTokens`, `temperature`, `topP` and blank prompts are validated before any request; `files()` on a provider without file storage throws `CapabilityNotSupportedException`.
- Credential redaction covers suffix-style parameters and AWS and GitHub token shapes.
- Allow Guzzle 8 alongside Guzzle 7 (`^7.15.2 || ^8.3.0`), tested on both in CI.

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
