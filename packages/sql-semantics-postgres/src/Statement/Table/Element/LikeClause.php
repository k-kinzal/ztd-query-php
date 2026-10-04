<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A LIKE clause: the new table takes the columns of another table at this position.
 *
 * Mirrors PostgreSQL's `TableLikeClause` with its options in the order
 * written. The source table is resolved (PG-TABLE-TARGET-001) and is the
 * relation fact of this clause; the new table declares the source's columns,
 * types and NOT NULL facts when the source is declared, and is incomplete
 * from this position on otherwise (PG-TABLE-DECLARATION-001).
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Copying the columns of a declared table
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
 *     $source = $semantics->analyze('CREATE TABLE u (id int NOT NULL, name text)', []);
 *     $copy = $semantics->analyze('CREATE TABLE t (LIKE u INCLUDING DEFAULTS, extra boolean)', $source->declarations());
 *     array_map(static fn ($column) => $column->name->value, $copy->declarations()[0]->columns) // => ['id', 'name', 'extra']
 */
final class LikeClause implements Clause
{
    use Snapshot;

    /**
     * @var list<LikeOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @param QualifiedName $table The source table
     * @param list<LikeOption> $options The options in the order written
     */
    public function __construct(public readonly QualifiedName $table, array $options = [])
    {
        $this->options = Check::listOf($options, LikeOption::class, 'LIKE options are INCLUDING and EXCLUDING options.');
    }

    /**
     * Resolves the source table and records it as the relation fact of the clause.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->target($this, (new Targets())->resolve($derivation, $this->table));
    }

    /**
     * Writes LIKE, the table and the options.
     */
    public function render(Output $out): void
    {
        $out->keyword('LIKE');
        (new Spelling())->qualified($out, $this->table);
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
