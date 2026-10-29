<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

/** A remote download was blocked, too large, redirected too often, or malformed. */
final class RemoteFetchException extends AiSdkException {}
