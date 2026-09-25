<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Sql;

/**
 * What one token of a SQL statement is.
 *
 * @visibility root
 */
enum SqlTokenKind: string
{
    case Word = 'word';
    case Number = 'number';
    case Text = 'text';
    case Identifier = 'identifier';
    case Comment = 'comment';
    case Parameter = 'parameter';
    case Symbol = 'symbol';
}
