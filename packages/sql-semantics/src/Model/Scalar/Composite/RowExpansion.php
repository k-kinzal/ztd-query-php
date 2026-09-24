<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Composite;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * PostgreSQL `(composite).*`: every field of a composite value, such as a row-typed parameter or a function result, taken in order.
 * Binding does not know the composite type, so the fields are not listed.
 *
 * @visibility public
 * @example Reading the expanded composite value
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('VALUES ($1.*)');
 *     $statement->rows[0][0]->composite->spelling() // => '$1'
 */
final class RowExpansion extends Expression
{
    /**
     * @param Expression $composite The composite-valued expression whose fields are expanded
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly Expression $composite)
    {
        if ($facts->type->dialect !== \SqlSemantics\Dialect::PostgreSql || $composite->type->dialect !== $facts->type->dialect) {
            throw new InvalidStructure('Row expansion of a composite value is a PostgreSQL form whose operand uses the same dialect.');
        }
        if ($composite instanceof self || $composite instanceof \SqlSemantics\Model\Scalar\Reference\Wildcard) {
            throw new InvalidStructure('Row expansion requires one composite value, not another expansion.');
        }
        parent::__construct($facts, $source);
    }

    /**
     * Returns the fixed semantic category.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::RowExpansion;
    }

    /**
     * @return list<Expression>
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->composite];
    }

    /**
     * Returns the expansion marker for diagnostics.
     */
    #[Override]
    public function spelling(): string
    {
        return '.*';
    }

    /**
     * Replaces derived type facts while retaining the composite operand.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->composite);
    }
}
