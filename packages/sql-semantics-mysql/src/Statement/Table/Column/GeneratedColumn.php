<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The specification of a generated column: its values are computed from an expression (MySQL 5.7 and later).
 *
 * Rule: MYSQL-GENERATED-COLUMN-001. The expression sees the columns of the
 * table (MYSQL-DEFINITION-SCOPE-001). Without VIRTUAL or STORED the column
 * is VIRTUAL; the storage is kept as written (null when absent). The words
 * GENERATED ALWAYS are optional and change nothing. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a generated column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT AS (a + 1) STORED)');
 *     $create->statement->elements[1]->specification->storage // => \SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage::Stored
 */
final class GeneratedColumn implements ColumnSpecification
{
    use Snapshot;

    /**
     * @var list<ColumnAttribute> The attributes in written order
     */
    public readonly array $attributes;

    /**
     * @param TypeName $type The data type
     * @param Scalar $expression The generation expression
     * @param CollationName|null $collation The collation written before AS
     * @param GeneratedStorage|null $storage VIRTUAL or STORED, when written
     * @param list<ColumnAttribute> $attributes The attributes in written order
     * @param References|null $references The inline reference, when written
     */
    public function __construct(
        public readonly TypeName $type,
        public readonly Scalar $expression,
        public readonly ?CollationName $collation = null,
        public readonly ?GeneratedStorage $storage = null,
        array $attributes = [],
        public readonly ?References $references = null,
    ) {
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
     * Derives the generation expression and the expressions of the attributes inside the table definition.
     */
    public function deriveSpecification(Derivation $derivation, Environment $scope): void
    {
        $derivation->scalar($this->expression, $scope);
        foreach ($this->attributes as $attribute) {
            $attribute->deriveAttribute($derivation, $scope);
        }
    }

    /**
     * Writes the type, the collation, the expression, the storage, the attributes and the reference.
     */
    public function render(Output $out): void
    {
        $out->node($this->type);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->node($this->collation);
        }
        $out->keyword('AS')->symbol('(')->node($this->expression)->symbol(')');
        if ($this->storage !== null) {
            $out->keyword($this->storage->value);
        }
        foreach ($this->attributes as $attribute) {
            $out->node($attribute);
        }
        $out->node($this->references);
    }
}
