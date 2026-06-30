<?php

declare(strict_types=1);

namespace AiSdk;

enum CapabilitySupportState: string
{
    case Supported = 'supported';
    case NotSupported = 'not_supported';
    case Adapted = 'adapted';
}
