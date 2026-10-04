<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\FromScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * Several FROM items separated by commas: their cross product.
 *
 * Mirrors the `fromClause` list of PostgreSQL's `SelectStmt` when it holds
 * more than one item; one item is kept as the item itself. Each item may be
 * referred to by the LATERAL items after it. The facts follow PG-FROM-SCOPE-001.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-FROM. Status: Implemented.
 *
 * @visibility public
 * @example Reading a FROM list
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 FROM a, b AS c');
 *     [count($query->statement->from->items), $query->toString()] // => [2, 'SELECT 1 FROM a, b AS c']
 * @example Refusing a list of one item
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Relation\RelationList([new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class RelationList implements Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<Relation> The FROM items in written order
     */
    public readonly array $items;

    /**
     * @param list<Relation> $items The FROM items in written order; at least two
     */
    public function __construct(array $items)
    {
        $this->items = Check::listOf($items, Relation::class, 'A FROM list holds at least two items.', 2);
        foreach ($this->items as $item) {
            Check::input(!$item instanceof self, 'A FROM list item is one FROM item.');
        }
    }

    /**
     * Derives every item and the combined columns.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new FromScope())->enter($this, $derivation, $environment, [])->fact;
    }

    /**
     * Writes the items separated by commas.
     */
    public function render(Output $out): void
    {
        $out->list($this->items);
    }
}
