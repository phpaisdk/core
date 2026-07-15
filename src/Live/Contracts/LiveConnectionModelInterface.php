<?php

declare(strict_types=1);

namespace AiSdk\Live\Contracts;

/**
 * Convenience contract for providers supporting every hosted connection
 * lifecycle. Providers may implement the granular contracts independently.
 */
interface LiveConnectionModelInterface extends LiveClientSecretModelInterface, LiveWebRtcModelInterface, LiveCallModelInterface {}
