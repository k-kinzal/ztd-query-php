<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER OPERATOR FAMILY name USING method ADD item, ...`: adds operators and support functions to a family.
 *
 * Mirrors PostgreSQL's `AlterOpFamilyStmt` with `isDrop` false. The grammar
 * accepts a STORAGE item, which the server rejects.
 * Source: https://www.postgresql.org/docs/17/sql-alteropfamily.html.
 *
 * @visibility public
 * @example Counting the added items
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER OPERATOR FAMILY f USING btree ADD OPERATOR 1 < (int4, int8)');
 *     count($operation->statement->items) // => 1
 */
final class OperatorFamilyAddition implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<OperatorClassItem> The added items in written order
     */
    public readonly array $items;

    /**
     * @param DottedName $name The family name
     * @param Name $method The index access method
     * @param list<OperatorClassItem> $items The added items; at least one
     */
    public function __construct(public readonly DottedName $name, public readonly Name $method, array $items)
    {
        $this->items = Check::listOf($items, OperatorClassItem::class, 'ADD names at least one item.', 1);
    }

    /**
     * Derives the items and reports a storage type.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $environment = $derivation->environment();
        foreach ($this->items as $item) {
            $item->deriveClause($derivation, $environment);
            if ($item instanceof StorageMember) {
                $derivation->report(new ObjectProblem(ObjectProblemKind::StorageInFamily));
            }
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'OPERATOR', 'FAMILY')->node($this->name)->keyword('USING')->name($this->method, NameUse::Column)->keyword('ADD')->list($this->items);
    }
}
