<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

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
 * A connection metadata field, without a fetched process or runtime state.
 * @visibility public
 * @example Inspecting the process result
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW PROCESSLIST');
 *     $statement->resultColumns()[0]->expression instanceof \SqlSemantics\Model\Query\Inspection\ProcessColumn // => true
 */
final class ProcessColumn extends Expression
{
    /**
     * Derives result facts from the declared metadata role and request.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly string $scopeId, public readonly ProcessField $field)
    {
        if ($scopeId === '') {
            throw new InvalidStructure('A process result field requires its producing statement identity.');
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, match ($field) {
            ProcessField::Connection => 'bigint', ProcessField::ElapsedTime => 'integer', ProcessField::User, ProcessField::Host, ProcessField::Database, ProcessField::Command, ProcessField::State => 'varchar'
        }), $field->nullability()), $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::ServerMetadata;
    }

    /**
     * @return list<Expression> No scalar inputs; the server supplies the process data
     */
    #[Override]
    public function inputs(): array
    {
        return [];
    }

    /**
     * Returns the result label, which has no standalone scalar SQL form.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->field->value;
    }

    /**
     * Preserves the facts derived from the inspection request.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Process result facts are derived from their field role.');
        }
        return new self($this->source, $this->scopeId, $this->field);
    }
}
