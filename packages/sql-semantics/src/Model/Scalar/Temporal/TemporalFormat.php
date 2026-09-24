<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * MySQL `GET_FORMAT(DATE | TIME | DATETIME, standard)`: the format string of a named standard such as 'EUR' or 'ISO' for one temporal kind; an unknown standard gives NULL.
 *
 * @visibility public
 * @example Reading the requested kind and standard
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SELECT GET_FORMAT(DATE, 'EUR')");
 *     $format = $statement->outputs[0]->expression;
 *     [$format->temporalKind->value, $format->standard->spelling()] // => ['DATE', "'EUR'"]
 */
final class TemporalFormat extends Expression
{
    /**
     * @param TemporalFormatKind $temporalKind The temporal kind whose format is requested
     * @param Expression $standard The standard name, such as 'EUR', 'USA', 'JIS', 'ISO' or 'INTERNAL'
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly TemporalFormatKind $temporalKind, public readonly Expression $standard)
    {
        if ($facts->type->dialect !== Dialect::MySql || $standard->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('GET_FORMAT is a MySQL function.');
        }
        parent::__construct($facts, $source);
    }

    /**
     * Returns the function category.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Function;
    }

    /**
     * @return list<Expression>
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->standard];
    }

    /**
     * Returns the function name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'GET_FORMAT';
    }

    /**
     * Replaces derived type facts while retaining the kind and standard.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->temporalKind, $this->standard);
    }
}
