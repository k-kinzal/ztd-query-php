<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Table;

use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Snapshot;

/**
 * A relation name that denotes exactly one declaration of the context.
 *
 * @visibility public
 * @example Reaching the supplied declaration object
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $query = $semantics->analyze('SELECT a FROM t', [$table]);
 *     $query->facts->relation($query->inputRelation())->table->table === $table->declarations()[0] // => true
 */
final class DeclaredTable implements TableResolution
{
    use Snapshot;

    /**
     * @param Table $table The declaration object of the context
     */
    public function __construct(public readonly Table $table)
    {
    }
}
