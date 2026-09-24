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
 * PostgreSQL JSON(): parses a character or binary string as a json value, without evaluating it.
 * WITH UNIQUE KEYS also rejects an object that repeats a key; a NULL input yields NULL.
 * @visibility public
 * @example Reading the parsed input and the key uniqueness
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT JSON('{\"a\": 1}' FORMAT JSON WITH UNIQUE KEYS)");
 *     $value = $query->outputs[0]->expression;
 *     [$value->input->format, $value->uniqueKeys, $value->type->name] // => [\SqlSemantics\Model\TableFunction\Json\Format::Json, true, 'json']
 */
final class JsonParse extends Expression
{
    /**
     * @param bool $uniqueKeys Whether WITH UNIQUE KEYS rejects an object that repeats a key
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Input $input, public readonly bool $uniqueKeys = false)
    {
        SqlJsonInvariant::postgreSql('JSON', [$input->expression]);
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'json'), $input->expression->nullability, $input->expression->nullExtendedBy), $source);
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
     * @return list<Expression> The parsed value
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
        return 'JSON';
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
            throw new InvalidStructure('JSON() facts are derived from its input.');
        }
        return new static($this->source, $this->input, $this->uniqueKeys);
    }
}
