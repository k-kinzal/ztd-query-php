<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The specification of a column that stores the values written to it: data type, attributes and an inline reference.
 *
 * Rule: MYSQL-ORDINARY-COLUMN-001. The attributes are kept in written order,
 * because the server applies them in that order (a later NULL cancels an
 * earlier NOT NULL). An inline REFERENCES clause is parsed and ignored by
 * the server; it is kept as a request. Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/ansi-diff-foreign-keys.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the attributes of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT NOT NULL DEFAULT 0)');
 *     count($create->statement->elements[0]->specification->attributes) // => 2
 */
final class OrdinaryColumn implements ColumnSpecification
{
    use Snapshot;

    /**
     * @var list<ColumnAttribute> The attributes in written order
     */
    public readonly array $attributes;

    /**
     * @param TypeName $type The data type
     * @param list<ColumnAttribute> $attributes The attributes in written order
     * @param References|null $references The inline reference, when written
     */
    public function __construct(public readonly TypeName $type, array $attributes = [], public readonly ?References $references = null)
    {
        $this->attributes = Check::listOf($attributes, ColumnAttribute::class, 'Column attributes are an ordered list of column attributes.');
    }

    /**
     * Answers the data type.
     */
    public function dataType(): TypeName
    {
        return $this->type;
    }

    /**
     * Answers the attributes in written order.
     *
     * @return list<ColumnAttribute>
     */
    public function columnAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Derives the expressions of the attributes inside the table definition.
     */
    public function deriveSpecification(Derivation $derivation, Environment $scope): void
    {
        foreach ($this->attributes as $attribute) {
            $attribute->deriveAttribute($derivation, $scope);
        }
    }

    /**
     * Writes the type, the attributes and the reference.
     */
    public function render(Output $out): void
    {
        $out->node($this->type);
        foreach ($this->attributes as $attribute) {
            $out->node($attribute);
        }
        $out->node($this->references);
    }
}
