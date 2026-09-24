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
use SqlSemantics\Model\TableFunction\Json\ArrayWrapping;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\TableFunction\Json\PassingArgument;
use SqlSemantics\Model\TableFunction\Json\Quotes;
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Model\TableFunction\Json\Response\ValueResponse;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * PostgreSQL JSON_QUERY: extracts the JSON item (or the array wrapping the items) at a path of a document, without evaluating it.
 * Without RETURNING the result is jsonb; without an ON EMPTY or ON ERROR response a missing or failing item yields NULL.
 * @visibility public
 * @example Reading the wrapper, quotes and responses
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT JSON_QUERY(jsonb '[1]', '$[*]' RETURNING text WITH CONDITIONAL WRAPPER EMPTY OBJECT ON ERROR)");
 *     $value = $query->outputs[0]->expression;
 *     [$value->wrapper, $value->quotes, $value->onError, $value->type->name] // => [\SqlSemantics\Model\TableFunction\Json\ArrayWrapping::Conditional, \SqlSemantics\Model\TableFunction\Json\Quotes::Default, \SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior::EmptyObject, 'text']
 */
final class JsonQueryExtraction extends Expression
{
    /**
     * @param list<PassingArgument> $passing Path variables in written order
     * @throws InvalidStructure
     */
    public function __construct(
        Node|Token $source,
        public readonly Input $document,
        public readonly Expression $path,
        public readonly array $passing = [],
        public readonly ?JsonReturning $returning = null,
        public readonly ArrayWrapping $wrapper = ArrayWrapping::Default,
        public readonly Quotes $quotes = Quotes::Default,
        public readonly ValueResponse $onEmpty = ValueBehavior::Default,
        public readonly ValueResponse $onError = ValueBehavior::Default,
    ) {
        Collections::objects($passing, PassingArgument::class);
        SqlJsonInvariant::postgreSql('JSON_QUERY', [$document->expression, $path, ...SqlJsonInvariant::passed($passing), ...SqlJsonInvariant::defaults($onEmpty, $onError)]);
        if (in_array($wrapper, [ArrayWrapping::Conditional, ArrayWrapping::Unconditional], true) && $quotes === Quotes::Omit) {
            throw new InvalidStructure('JSON_QUERY cannot omit quotes when WITH WRAPPER is used.');
        }
        parent::__construct(new ExpressionFacts($returning->type ?? TypeDescriptor::builtin(Dialect::PostgreSql, 'jsonb'), Nullability::MaybeNull, $document->expression->nullExtendedBy), $source);
    }

    /**
     * Identifies a JSON item extraction.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::JsonQuery;
    }

    /**
     * @return list<Expression> The document, the path, the PASSING values and any DEFAULT response values
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->document->expression, $this->path, ...SqlJsonInvariant::passed($this->passing), ...SqlJsonInvariant::defaults($this->onEmpty, $this->onError)];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'JSON_QUERY';
    }

    /**
     * Preserves the facts derived from the returned type.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('JSON_QUERY facts are derived from its returned type.');
        }
        return new static($this->source, $this->document, $this->path, $this->passing, $this->returning, $this->wrapper, $this->quotes, $this->onEmpty, $this->onError);
    }
}
