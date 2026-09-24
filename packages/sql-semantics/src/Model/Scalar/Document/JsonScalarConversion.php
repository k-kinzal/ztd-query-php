<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document;

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
 * PostgreSQL JSON_SCALAR: converts an SQL scalar value into a json scalar, without evaluating it; a NULL input yields SQL NULL.
 * @visibility public
 * @example Reading the converted value
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT JSON_SCALAR(1)');
 *     $value = $query->outputs[0]->expression;
 *     [$value->value->spelling(), $value->type->name] // => ['1', 'json']
 */
final class JsonScalarConversion extends Expression
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $value)
    {
        SqlJsonInvariant::postgreSql('JSON_SCALAR', [$value]);
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'json'), $value->nullability, $value->nullExtendedBy), $source);
    }

    /**
     * Identifies a SQL/JSON conversion.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::JsonConversion;
    }

    /**
     * @return list<Expression> The converted value
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'JSON_SCALAR';
    }

    /**
     * Preserves the json facts derived from the input.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('JSON_SCALAR facts are derived from its input.');
        }
        return new static($this->source, $this->value);
    }
}
