<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Qualifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * Constraints added to a column a typed table or a partition takes from its type or parent.
 *
 * Mirrors the `ColumnDef` without a type that `columnOptions` builds. The
 * optional WITH OPTIONS words change nothing ("column_name [ WITH OPTIONS ]
 * [ column_constraint [ ... ] ]") and are not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Adding a constraint to a column of a partition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE p1 PARTITION OF p (a WITH OPTIONS NOT NULL) DEFAULT');
 *     $create->toString() // => 'CREATE TABLE p1 PARTITION OF p (a NOT NULL) DEFAULT'
 */
final class ColumnOptions implements Clause
{
    use Snapshot;

    /**
     * @var list<Clause> The constraints, deferral attributes and collation, in the order written
     */
    public readonly array $qualifiers;

    /**
     * @param Name $name The column name
     * @param list<Clause> $qualifiers The constraints, deferral attributes and collation, in the order written
     */
    public function __construct(public readonly Name $name, array $qualifiers = [])
    {
        $this->qualifiers = (new Qualifiers())->checked($qualifiers);
    }

    /**
     * Derives the qualifiers in the environment of the table and reports their problems.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Qualifiers())->derive($derivation, $environment, $this->name, $this->qualifiers);
    }

    /**
     * Writes the column name and the qualifiers.
     */
    public function render(Output $out): void
    {
        $out->name($this->name);
        foreach ($this->qualifiers as $qualifier) {
            $out->node($qualifier);
        }
    }
}
