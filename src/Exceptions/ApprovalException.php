<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

/**
 * An approval could not be requested or resolved: the token is invalid, expired or already used, the decisions
 * are incomplete, or a tool that needs approval ran outside an Agent. The message never contains the token.
 */
final class ApprovalException extends AiSdkException {}
