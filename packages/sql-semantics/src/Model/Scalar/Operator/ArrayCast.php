<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A MySQL `CAST(json AS type ARRAY)` that converts every element of a JSON array, as a multi-valued index key.
 * @visibility public
 * @example Reading the element type of a multi-valued index key
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE TABLE t (j JSON, INDEX ((CAST(j->'$.tags' AS CHAR(8) ARRAY))))");
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => "CREATE TABLE `t`(`j` json, INDEX((CAST((`j` -> '$.tags') AS CHAR(8) ARRAY))))"
 */
final class ArrayCast extends Expression
{
    /**
     * The result carries the element type; each element keeps the NULL behavior of the JSON input.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $operand, public readonly TypeDescriptor $element)
    {
        if ($operand->type->dialect !== Dialect::MySql || $element->dialect !== Dialect::MySql) {
            throw new InvalidStructure('Array casts require MySQL.');
        }
        parent::__construct(new ExpressionFacts($element, $operand->nullability, $operand->nullExtendedBy), $source);
    }

    /**
     * Identifies a conversion.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Cast;
    }

    /**
     * @return list<Expression> The converted JSON array
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->operand];
    }

    /**
     * Returns the conversion name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'CAST';
    }

    /**
     * Preserves the facts derived from the element type and the operand.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Array cast facts are derived from its element type.');
        }
        return new static($this->source, $this->operand, $this->element);
    }
}
