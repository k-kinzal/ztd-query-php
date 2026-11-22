<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * `CREATE OPERATOR CLASS name [DEFAULT] FOR TYPE type USING method [FAMILY family] AS item, ...`: defines an operator class.
 *
 * Mirrors PostgreSQL's `CreateOpClassStmt` and `CreateOpClassItem`.
 * Source: https://www.postgresql.org/docs/17/sql-createopclass.html.
 *
 * @visibility SqlSemantics
 */
final class CreateOperatorClass implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<OperatorClassItem> The items in written order
     */
    public readonly array $items;

    /**
     * @param DottedName $name The class name
     * @param TypeName $type The indexed data type
     * @param Name $method The index access method
     * @param list<OperatorClassItem> $items The operators, support functions and storage type; at least one
     * @param bool $isDefault Whether DEFAULT is written: the class is the default for the type
     * @param DottedName|null $family The operator family the class joins, if written
     */
    public function __construct(
        public readonly DottedName $name,
        public readonly TypeName $type,
        public readonly Name $method,
        array $items,
        public readonly bool $isDefault = false,
        public readonly ?DottedName $family = null,
    ) {
        $this->items = Check::listOf($items, OperatorClassItem::class, 'An operator class has at least one item.', 1);
    }

    /**
     * Derives the type and the items.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $environment = $derivation->environment();
        $this->type->deriveClause($derivation, $environment);
        foreach ($this->items as $item) {
            $item->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'OPERATOR', 'CLASS')->node($this->name);
        if ($this->isDefault) {
            $out->keyword('DEFAULT');
        }
        $out->keyword('FOR', 'TYPE')->node($this->type)->keyword('USING')->name($this->method, NameUse::Column);
        if ($this->family !== null) {
            $out->keyword('FAMILY')->node($this->family);
        }
        $out->keyword('AS')->list($this->items);
    }
}
