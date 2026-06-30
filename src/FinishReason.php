<?php

declare(strict_types=1);

namespace AiSdk;

enum FinishReason: string
{
    case Stop = 'stop';
    case Length = 'length';
    case ToolCalls = 'tool-calls';
    case ContentFilter = 'content-filter';
    case Error = 'error';
    case Unknown = 'unknown';
}
