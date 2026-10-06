<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * One option of a sequence or of an identity column's sequence.
 *
 * Mirrors the `DefElem` list `SeqOptElem` builds; each implementation is one
 * group of options with the same operand.
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html.
 *
 * @visibility public
 * @example Reading the options of a sequence
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SEQUENCE s INCREMENT BY 2 NO CYCLE');
 *     count($create->statement->options) // => 2
 */
interface SequenceOption extends Clause
{
}
