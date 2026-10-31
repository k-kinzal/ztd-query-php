<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * The specification of a column that stores the values written to it: data type, attributes and an inline reference.
 *
 * Rule: MYSQL-ORDINARY-COLUMN-001. The attributes are kept in written order,
 * because the server applies them in that order (a later NULL cancels an
 * earlier NOT NULL). Effective clock defaults and updates require DATETIME or
 * TIMESTAMP with matching precision; SRID requires a spatial type. AUTO_INCREMENT
 * requires an integer or floating type (floating types are accepted only by legacy releases).
 * Incompatible attributes remain part of the declaration request and produce
 * an InvalidColumnAttribute condition when the enclosing statement is derived.
 * An inline REFERENCES clause is ignored through MySQL 8.4 and declares a
 * foreign key from MySQL 9.0 onward; the reference request is retained. Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/ansi-diff-foreign-keys.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the attributes of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT NOT NULL DEFAULT 0)');
 *     count($create->elements[0]->specification->attributes) // => 2
 */
final class OrdinaryColumn implements ColumnSpecification
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<ColumnAttribute> The attributes in written order
     */
    public readonly array $attributes;

    /**
     * @param DeclaredName $type The data type
     * @param list<ColumnAttribute> $attributes The attributes in written order
     * @param References|null $references The inline reference, when written
     */
    public function __construct(public readonly DeclaredName $type, array $attributes = [], public readonly ?References $references = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        $this->attributes = Check::listOf($attributes, ColumnAttribute::class, 'Column attributes are an ordered list of column attributes.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnAttributes())->enforcement($this->attributes) === null, 'Column enforcement follows a CHECK constraint or its enforcement chain.');
    }

    /**
     * Answers the data type.
     */
    public function dataType(): DeclaredName
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
