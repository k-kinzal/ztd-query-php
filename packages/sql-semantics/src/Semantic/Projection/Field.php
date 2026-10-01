<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Projection;

use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Semantic\Expression\Binary;
use SqlSemantics\Semantic\Expression\Coalesce;
use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Expression\Literal;
use SqlSemantics\Semantic\Expression\NullIf;
use SqlSemantics\Semantic\Expression\Operands;
use SqlSemantics\Semantic\Expression\Parameter;
use SqlSemantics\Semantic\Expression\Unary;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\Type\InvalidReference;
use SqlSemantics\Semantic\Type\Undetermined;

/**
 * One result position, with an expression and an optional explicit alias.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT foo AS result FROM bar');
 *     $statement->field('result')->expression->name->value // => 'foo'
 *
 * @visibility public
 */
final class Field
{
    /**
     * The semantic name exposed by this value.
     */
    public readonly ?Name $name;
    /**
     * The result type or the explicit reason no type can be established.
     */
    public readonly TypeDescriptor|Undetermined|InvalidReference $type;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf $expression, public readonly ?Name $alias = null)
    {
        $this->name = $alias ?? ($expression instanceof ColumnReference ? $expression->name : null);
        $this->type = $expression instanceof Literal ? Operands::projectedType($expression) : $expression->type;
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return $this->expression->toString() . ($this->alias === null ? '' : ' AS ' . $this->alias->toString());
    }
}
