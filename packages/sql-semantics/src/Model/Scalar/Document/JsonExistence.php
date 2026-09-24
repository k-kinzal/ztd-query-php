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
use SqlSemantics\Model\TableFunction\Json\PassingArgument;
use SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * PostgreSQL JSON_EXISTS: whether a path of a document yields any item, as a boolean, without evaluating it.
 * Without an ON ERROR response a failing path yields false.
 * @visibility public
 * @example Reading the path variables and the error response
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT JSON_EXISTS(jsonb '[1]', '$[\$i]' PASSING 0 AS i UNKNOWN ON ERROR)");
 *     $value = $query->outputs[0]->expression;
 *     [$value->passing[0]->name, $value->onError, $value->type->name] // => ['i', \SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse::Unknown, 'boolean']
 */
final class JsonExistence extends Expression
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
        public readonly ExistsResponse $onError = ExistsResponse::Default,
    ) {
        Collections::objects($passing, PassingArgument::class);
        SqlJsonInvariant::postgreSql('JSON_EXISTS', [$document->expression, $path, ...SqlJsonInvariant::passed($passing)]);
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull, $document->expression->nullExtendedBy), $source);
    }

    /**
     * Identifies a JSON path existence test.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::JsonExists;
    }

    /**
     * @return list<Expression> The document, the path and the PASSING values
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->document->expression, $this->path, ...SqlJsonInvariant::passed($this->passing)];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'JSON_EXISTS';
    }

    /**
     * Preserves the boolean facts of the test.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('JSON_EXISTS facts are derived from the test.');
        }
        return new static($this->source, $this->document, $this->path, $this->passing, $this->onError);
    }
}
