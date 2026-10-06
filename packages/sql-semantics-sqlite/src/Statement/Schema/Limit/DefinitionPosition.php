<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Limit;

/**
 * The positions inside a definition whose expressions SQLite resolves against the table itself, each with its own limits.
 *
 * The value is the phrase SQLite uses for the position in its error message.
 * Source: https://sqlite.org/lang_createtable.html#check_constraints,
 * https://sqlite.org/partialindex.html, https://sqlite.org/expridx.html,
 * https://sqlite.org/gencol.html.
 *
 * @visibility public
 * @example Reading the position of a prohibited expression
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a CHECK (a > ?))');
 *     $create->facts->diagnostics[0]->position // => \SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefinitionPosition::CheckConstraint
 */
enum DefinitionPosition: string
{
    case CheckConstraint = 'CHECK constraints';
    case PartialIndexWhere = 'partial index WHERE clauses';
    case IndexExpression = 'index expressions';
    case GeneratedColumn = 'generated columns';
}
