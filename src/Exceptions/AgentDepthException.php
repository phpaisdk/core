<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

/** A nested agent exceeded the shared depth ceiling or revisited an ancestor. */
final class AgentDepthException extends AiSdkException {}
