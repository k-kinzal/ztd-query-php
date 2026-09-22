<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * PostgreSQL's greatest or least non-NULL argument after common-type conversion.
 * @visibility public
 * @example Reading the comparison direction
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT GREATEST(1, 2)');
 *     $query->outputs[0]->expression->selection->value // => 'GREATEST'
 */
final class Extremum extends Expression
{
    /**
     * @var non-empty-list<Expression> Candidates converted to the result type
     */
    public readonly array $arguments;

    /**
     * @param list<Expression> $arguments
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly ExtremumKind $selection, array $arguments)
    {
        if ($facts->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This conditional operation requires PostgreSQL.');
        }
        Collections::objects($arguments, Expression::class);
        $this->arguments = Collections::nonEmpty($arguments);
        StatementOperands::expressions($arguments, $facts->type->dialect);
        parent::__construct($facts, $source);
    }

    /**
     * Returns the conditional extremum operation category.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Extremum;
    }

    /**
     * @return non-empty-list<Expression> Ordered candidates without evaluating their values
     */
    #[Override]
    public function inputs(): array
    {
        return $this->arguments;
    }

    /**
     * Returns the fixed SQL operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->selection->value;
    }

    /**
     * Preserves every candidate and the comparison direction when replacing result facts.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->selection, $this->arguments);
    }
}
