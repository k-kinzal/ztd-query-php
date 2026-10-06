<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\OptionRules;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CLUSTER`: a request to reorder a table by an index, or every previously clustered table.
 *
 * Rule: PG-CLUSTER-001. Mirrors PostgreSQL's `ClusterStmt` (relation,
 * indexname, params). Without a table every table clustered before is
 * processed again. The syntax of releases before 8.3, `CLUSTER index ON
 * table`, makes the same request as `CLUSTER table USING index` and is kept
 * as written. Facts: the table is resolved; the index is not part of a
 * declaration context. A relation declared as another kind than a table or
 * a materialized view is reported (PG-RELATION-KIND-001). Diagnostic: an option CLUSTER does not know.
 * Source: https://www.postgresql.org/docs/17/sql-cluster.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a CLUSTER in the old syntax
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CLUSTER i ON t');
 *     [$operation->statement->table->name->value, $operation->statement->index->value, $operation->statement->indexFirst] // => ['t', 'i', true]
 * @example Refusing an index without a table
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Cluster([], \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionSyntax::Words, null, new \SqlSemantics\Statement\Identifier\Name('i')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Cluster implements Statement
{
    use Snapshot;

    /**
     * @var list<UtilityOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @param list<UtilityOption> $options The options in the order written
     * @param OptionSyntax $syntax How the options are written
     * @param QualifiedName|null $table The table; null for every previously clustered table
     * @param Name|null $index The index to cluster by; null for the index used before
     * @param bool $indexFirst Whether the old syntax `index ON table` is written
     */
    public function __construct(
        array $options = [],
        public readonly OptionSyntax $syntax = OptionSyntax::Words,
        public readonly ?QualifiedName $table = null,
        public readonly ?Name $index = null,
        public readonly bool $indexFirst = false,
    ) {
        $this->options = Check::listOf($options, UtilityOption::class, 'The options of CLUSTER are utility options.');
        Check::input((new OptionRules())->writable($this->options, $syntax, ['verbose']), 'The options cannot be written in that syntax.');
        Check::input($index === null || $table !== null, 'An index is named together with its table.');
        Check::input(!$indexFirst || ($index !== null && $syntax === OptionSyntax::Words), 'The old syntax names an index and takes no parenthesized options.');
    }

    /**
     * Resolves the table and reports what CLUSTER rejects.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new OptionRules())->derive($derivation, 'CLUSTER', $this->options);
        if ($this->table !== null) {
            $kinds = new RelationKinds();
            $kind = $kinds->of($derivation->target($this, (new Targets())->resolve($derivation, $this->table)));
            $kinds->require($derivation, $kind, $this->table->name, [RelationKind::BaseTable, RelationKind::MaterializedView], KindRule::NotTableOrMaterializedView);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CLUSTER');
        (new OptionRules())->write($out, $this->options, $this->syntax);
        if ($this->table === null) {
            return;
        }
        if ($this->indexFirst && $this->index !== null) {
            $out->name($this->index, NameUse::Column)->keyword('ON');
            (new Spelling())->qualified($out, $this->table);

            return;
        }
        (new Spelling())->qualified($out, $this->table);
        if ($this->index !== null) {
            $out->keyword('USING')->name($this->index, NameUse::Column);
        }
    }
}
