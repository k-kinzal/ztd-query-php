<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Construction;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Document\JsonReturning;
use SqlSemantics\Model\Scalar\Document\SqlJsonInvariant;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * PostgreSQL SQL/JSON JSON_ARRAY over a list of values: builds an array of the elements in order, without evaluating them.
 * The result is json unless RETURNING says otherwise, and is never NULL. Without elements there is no NULL clause.
 * @visibility public
 * @example Reading the elements and the NULL handling
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT JSON_ARRAY(1, NULL NULL ON NULL RETURNING jsonb)');
 *     $value = $query->outputs[0]->expression;
 *     [count($value->elements), $value->onNull, $value->type->name] // => [2, \SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling::Null, 'jsonb']
 */
final class JsonArrayConstructor extends Expression
{
    /**
     * @param list<Input> $elements Ordered elements; an empty list is `JSON_ARRAY()`
     * @throws InvalidStructure
     */
    public function __construct(
        Node|Token $source,
        public readonly array $elements,
        public readonly JsonNullHandling $onNull = JsonNullHandling::Absent,
        public readonly ?JsonReturning $returning = null,
    ) {
        Collections::objects($elements, Input::class);
        if ($elements === [] && $onNull !== JsonNullHandling::Absent) {
            throw new InvalidStructure('JSON_ARRAY() without elements has no NULL clause.');
        }
        SqlJsonInvariant::postgreSql('JSON_ARRAY', SqlJsonInvariant::values($elements));
        parent::__construct(new ExpressionFacts($returning->type ?? TypeDescriptor::builtin(Dialect::PostgreSql, 'json'), Nullability::NotNull), $source);
    }

    /**
     * Identifies a SQL/JSON constructor.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::JsonConstructor;
    }

    /**
     * @return list<Expression> The element values in written order
     */
    #[Override]
    public function inputs(): array
    {
        return SqlJsonInvariant::values($this->elements);
    }

    /**
     * Returns the fixed constructor name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'JSON_ARRAY';
    }

    /**
     * Preserves the non-NULL facts of the constructed array.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('JSON_ARRAY facts are derived from its returned type.');
        }
        return new static($this->source, $this->elements, $this->onNull, $this->returning);
    }
}
