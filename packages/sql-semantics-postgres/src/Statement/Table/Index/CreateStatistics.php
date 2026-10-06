<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Index;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\KeyTerms;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\StatisticsScope;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create extended statistics on columns or expressions of a table.
 *
 * Mirrors PostgreSQL's `CreateStatsStmt` (`defnames`, `stat_types`, `exprs`, `relations`, `if_not_exists`).
 * The FROM items are derived as in a query (the server accepts one table); the columns and expressions are
 * derived where they are visible. A single table declared as a view or a sequence is reported
 * (PG-RELATION-KIND-001).
 * Source: https://www.postgresql.org/docs/17/sql-createstatistics.html.
 *
 * @visibility public
 * @example Creating statistics
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE STATISTICS IF NOT EXISTS s (ndistinct) ON a, (a + b) FROM t');
 *     $statement->toString() // => 'CREATE STATISTICS IF NOT EXISTS s (ndistinct) ON a, (a + b) FROM t'
 */
final class CreateStatistics implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<ColumnKey|ExpressionKey> The columns and expressions
     */
    public readonly array $keys;

    /**
     * @var non-empty-list<Relation> The FROM items
     */
    public readonly array $from;

    /**
     * @var list<Name> The kinds of statistics; none means all
     */
    public readonly array $kinds;

    /**
     * @param DottedName|null $name The statistics name; required with IF NOT EXISTS
     * @param list<ColumnKey|ExpressionKey> $keys The columns and expressions
     * @param list<Relation> $from The FROM items
     * @param list<Name> $kinds The kinds of statistics; none means all
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(
        public readonly ?DottedName $name,
        array $keys,
        array $from,
        array $kinds = [],
        public readonly bool $ifNotExists = false,
    ) {
        $this->keys = (new KeyTerms())->checked($keys);
        $this->from = Check::listOf($from, Relation::class, 'Statistics are built FROM at least one relation.', 1);
        $this->kinds = Check::listOf($kinds, Name::class, 'Statistics kinds are names.');
        Check::input(!$ifNotExists || $name !== null, 'IF NOT EXISTS needs a statistics name.');
    }

    /**
     * Derives the FROM items, then the columns and expressions where they are visible.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $scope = (new StatisticsScope())->derive($this->from, $derivation);
        $kinds = new RelationKinds();
        foreach (count($this->from) === 1 ? $this->from : [] as $item) {
            if ($item instanceof TableInput) {
                $kinds->require($derivation, $kinds->declared($derivation, $item->name()), $item->name()->name, [RelationKind::BaseTable, RelationKind::MaterializedView, RelationKind::ForeignTable], KindRule::StatisticsRelation);
            }
        }
        foreach ($this->keys as $key) {
            $derivation->scalar($key, $scope);
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'STATISTICS');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->node($this->name);
        if ($this->kinds !== []) {
            (new Writing())->parenthesized($out, $this->kinds);
        }
        $out->keyword('ON')->list($this->keys)->keyword('FROM')->list($this->from);
    }
}
