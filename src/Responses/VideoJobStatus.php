<?php

declare(strict_types=1);

namespace AiSdk\Responses;

enum VideoJobStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Expired = 'expired';
}
