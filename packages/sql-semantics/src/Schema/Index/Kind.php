<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Index;

/**
 * Kind alternatives.
 *
 * @visibility public
 */
enum Kind: string
{
    case Ordinary = 'ordinary';
    case FullText = 'fulltext';
    case Spatial = 'spatial';
}
