<?php

declare(strict_types=1);

namespace AiSdk\Live\Contracts;

use AiSdk\Live\LiveRequest;
use AiSdk\Live\WebRtcAnswer;

/** Optional WebRTC signalling lifecycle implemented by capable providers. */
interface LiveWebRtcModelInterface extends LiveModelInterface
{
    public function webRtc(LiveRequest $request, string $offerSdp): WebRtcAnswer;
}
