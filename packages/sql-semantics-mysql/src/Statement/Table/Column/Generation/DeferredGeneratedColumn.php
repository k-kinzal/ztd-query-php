<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column\Generation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;

/**
 * A complete generation declaration with operands retained until invocation.
 *
 * Stored definitions retain this column template until their table instruction
 * is invoked. Complete CREATE TABLE and ALTER TABLE requests can also retain it.
 * Aggregates and window calls retain their invocation-time role across releases.
 * MySQL 5.7 prepared compound expressions can retain markers whose bound values become
 * constants in the created table. A bare marker has no such declaration role.
 *
 * @visibility public
 * @example Retaining a scalar-position subquery for a stored definition
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
 *     $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int), new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery($query)));
 *     $column->expression instanceof \SqlSemantics\Statement\Scalar // => true
 */
final class DeferredGeneratedColumn implements ColumnSpecification
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<ColumnAttribute> The attributes in written order
     */
    public readonly array $attributes;

    /**
     * @param DeclaredName $type The data type
     * @param Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $expression The generation expression
     * @param CollationName|null $collation The collation written before AS
     * @param GeneratedStorage|null $storage VIRTUAL or STORED, when written
     * @param list<ColumnAttribute> $attributes The attributes in written order
     * @param References|null $references The inline reference, when written
     */
    public function __construct(
        public readonly DeclaredName $type,
        public readonly Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $expression,
        public readonly ?CollationName $collation = null,
        public readonly ?GeneratedStorage $storage = null,
        array $attributes = [],
        public readonly ?References $references = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        public readonly \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords $generatedAlways = \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted,
    ) {
        Check::input($collation === null || $collation->name !== null, 'A generated column names an explicit collation.');
        $this->attributes = Check::listOf($attributes, ColumnAttribute::class, 'Column attributes are an ordered list of column attributes.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnAttributes())->enforcement($this->attributes) === null, 'Column enforcement follows a CHECK constraint or its enforcement chain.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacyExpressions())->contains($expression) || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\GroupedOperands())->deferred($expression), 'A deferred generation declaration retains an invocation-time operand condition.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
     * Derives the generation expression and the expressions of the attributes inside the table definition.
     */
    public function deriveSpecification(Derivation $derivation, Environment $scope): void
    {
        if ($this->collation !== null) {
            (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts())->encoding($this->collation, $derivation, $this);
        }
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
        if ($this->generatedAlways === \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written) {
            $out->keyword('GENERATED')->keyword('ALWAYS');
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
