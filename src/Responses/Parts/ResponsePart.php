<?php

declare(strict_types=1);

namespace AiSdk\Responses\Parts;

/**
 * Canonical, typed content part emitted by providers and consumed by
 * responses. Replaces the loose array<string, mixed> "type" maps so callers
 * never guess key names.
 */
abstract class ResponsePart {}
