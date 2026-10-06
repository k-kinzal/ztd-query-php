<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Maintenance;

/**
 * A keyword the grammar accepts as a pragma value although it is reserved elsewhere.
 *
 * Source: https://sqlite.org/syntax/pragma-value.html.
 *
 * @visibility public
 * @example Reading a keyword value
 *     $pragma = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('PRAGMA journal_mode = DELETE');
 *     $pragma->statement->value // => \SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaKeyword::Delete
 */
enum PragmaKeyword: string
{
    case On = 'ON';
    case Delete = 'DELETE';
    case Default = 'DEFAULT';
}
