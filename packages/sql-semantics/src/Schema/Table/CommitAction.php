<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

/**
 * CommitAction alternatives.
 *
 * @visibility public
 */
enum CommitAction: string
{
    case PreserveRows = 'preserve-rows';
    case DeleteRows = 'delete-rows';
    case Drop = 'drop';
}
