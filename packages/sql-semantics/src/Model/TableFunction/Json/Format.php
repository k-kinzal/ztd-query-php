<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * A classified SQL/JSON format policy.
 * @visibility public
 */
enum Format: string
{
    case Json = 'FORMAT JSON';
    case Utf8 = 'FORMAT JSON ENCODING UTF8';
    case Utf16 = 'FORMAT JSON ENCODING UTF16';
    case Utf32 = 'FORMAT JSON ENCODING UTF32';
}
