<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

/**
 * The kind of an index.
 *
 * @visibility MySqlMemory
 */
enum KeyKind
{
    case Primary;
    case Unique;
    case Index;
    case FullText;
    case Spatial;
}
