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
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * PostgreSQL JSON_SERIALIZE: writes a JSON value as a character or binary string, without evaluating it.
 * Without RETURNING the result is text; a NULL input yields NULL.
 * @visibility public
 * @example Reading the input format and the returned type
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT JSON_SERIALIZE('{}' FORMAT JSON RETURNING bytea)");
 *     $value = $query->outputs[0]->expression;
 *     [$value->input->format, $value->returning?->type->name, $value->type->name] // => [\SqlSemantics\Model\TableFunction\Json\Format::Json, 'bytea', 'bytea']
 */
final class JsonSerialization extends Expression
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Input $input, public readonly ?JsonReturning $returning = null)
    {
        SqlJsonInvariant::postgreSql('JSON_SERIALIZE', [$input->expression]);
        parent::__construct(new ExpressionFacts($returning->type ?? TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), $input->expression->nullability, $input->expression->nullExtendedBy), $source);
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
     * @return list<Expression> The serialized value
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->input->expression];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'JSON_SERIALIZE';
    }

    /**
     * Preserves the facts derived from the input and the returned type.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('JSON_SERIALIZE facts are derived from its input and returned type.');
        }
        return new static($this->source, $this->input, $this->returning);
    }
}
