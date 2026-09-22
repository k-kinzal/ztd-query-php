<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * GeneratedStorage alternatives.
 *
 * @visibility public
 */
enum GeneratedStorage: string
{
    case Virtual = 'virtual';
    case Stored = 'stored';
}
