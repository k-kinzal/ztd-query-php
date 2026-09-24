<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Value;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\ArrayDimension;
use SqlSemantics\Type\Identity\ArrayStorage;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A PostgreSQL ARRAY[...] constructor; nested constructors add dimensions to the same element type.
 * @visibility public
 * @example Reading the elements and the derived array type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT ARRAY[1, 2]');
 *     count($statement->outputs[0]->expression->elements) // => 2
 *     $statement->outputs[0]->expression->type->name // => 'integer[]'
 */
final class ArrayConstructor extends Expression
{
    /**
     * Derives a non-NULL array of the common element type; an element that is itself an array keeps its type.
     * @param list<Expression> $elements Ordered elements; empty only when a cast supplies the type
     * @param TypeDescriptor $element Common type of the elements
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly TypeDescriptor $element, public readonly array $elements)
    {
        Collections::objects($elements, Expression::class);
        if ($element->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('An ARRAY constructor requires PostgreSQL.');
        }
        foreach ($elements as $item) {
            if ($item->type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('Expression operands cannot mix SQL dialects.');
            }
        }
        parent::__construct(new ExpressionFacts(self::arrayOf($element), Nullability::NotNull), $source);
    }

    /**
     * Returns the array type holding values of the element type; an unknown element type stays unknown.
     * @throws InvalidStructure
     */
    public static function arrayOf(TypeDescriptor $element): TypeDescriptor
    {
        if ($element->name === 'unknown' || $element->identity instanceof ArrayStorage) {
            return $element;
        }
        return new TypeDescriptor(Dialect::PostgreSql, new ArrayStorage($element, [new ArrayDimension()]));
    }

    /**
     * Identifies an array constructor.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::ArrayConstructor;
    }

    /**
     * @return list<Expression> The elements in written order
     */
    #[Override]
    public function inputs(): array
    {
        return $this->elements;
    }

    /**
     * Returns the fixed constructor keyword.
     */
    #[Override]
    public function spelling(): string
    {
        return 'ARRAY';
    }

    /**
     * Preserves the facts derived from the element type.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Array constructor facts are derived from its elements.');
        }
        return new static($this->source, $this->element, $this->elements);
    }
}
