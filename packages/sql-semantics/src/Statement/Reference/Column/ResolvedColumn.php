<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Column;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Snapshot;

/**
 * A name that resolves to exactly one output slot of one relation occurrence.
 *
 * @visibility public
 * @example Reaching the occurrence and the supplied declaration
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $query = $semantics->analyze('SELECT a FROM t', [$table]);
 *     $resolution = $query->field('a')->resolution;
 *     [$resolution->relation === $query->inputRelation(), $resolution->declaration() === $table->declarations()[0]->columns[0], $resolution->depth] // => [true, true, 0]
 */
final class ResolvedColumn implements Resolution
{
    use Snapshot;

    /**
     * @param Relation $relation The relation occurrence the name was found in
     * @param OutputSlot $slot The output slot of that occurrence
     * @param int $depth How many enclosing queries lie between the use and the occurrence; zero for the same query
     */
    public function __construct(
        public readonly Relation $relation,
        public readonly OutputSlot $slot,
        public readonly int $depth = 0,
    ) {
        Check::input($depth >= 0, 'A correlation depth is not negative.');
    }

    /**
     * Answers the declared column, when the slot is one.
     */
    public function declaration(): ?Column
    {
        return $this->slot->declaration();
    }
}
