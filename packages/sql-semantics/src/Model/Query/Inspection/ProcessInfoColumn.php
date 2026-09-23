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
 * The requested active-query text, with the SHOW PROCESSLIST truncation policy.
 * @visibility public
 * @example Inspecting the process result
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW PROCESSLIST');
 *     $statement->resultColumns()[7]->expression instanceof \SqlSemantics\Model\Query\Inspection\ProcessInfoColumn // => true
 */
final class ProcessInfoColumn extends Expression
{
    /**
     * Derives result facts from the declared metadata role and request.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly string $scopeId, public readonly ProcessQueryText $detail)
    {
        if ($scopeId === '') {
            throw new InvalidStructure('A process result field requires its producing statement identity.');
        }
        parent::__construct(new ExpressionFacts(new TypeDescriptor(Dialect::MySql, new \SqlSemantics\Type\Identity\StringStorage(\SqlSemantics\Type\Identity\BuiltinIdentity::Varchar, $detail === ProcessQueryText::Preview ? new \SqlSemantics\Type\Identity\Numeric\NumericParameter('100') : null)), \SqlSemantics\Type\Nullability::MaybeNull), $source);
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
        return 'Info';
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
        return new self($this->source, $this->scopeId, $this->detail);
    }
}
