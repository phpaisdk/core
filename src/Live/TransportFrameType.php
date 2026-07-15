<?php

declare(strict_types=1);

namespace AiSdk\Live;

enum TransportFrameType: string
{
    case Text = 'text';
    case Binary = 'binary';
}
