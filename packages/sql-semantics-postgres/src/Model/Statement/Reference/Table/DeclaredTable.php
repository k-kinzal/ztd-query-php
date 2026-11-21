<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A relation name that denotes exactly one declaration of the context.
 *
 * @visibility public
 * @example Reaching the supplied declaration object
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
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
