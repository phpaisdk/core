<?php

declare(strict_types=1);

namespace AiSdk;

enum ContentSource: string
{
    case Text = 'text';
    case Url = 'url';
    case DataUri = 'data_uri';
    case Base64 = 'base64';
    case Raw = 'raw';
}
