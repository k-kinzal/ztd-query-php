<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Xa;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A server-produced field of one prepared XA branch, rather than a scalar SQL operand.
 * @visibility public
 * @example Inspecting the recovery data field
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('XA RECOVER CONVERT XID');
 *     $statement->resultColumns()[3]->expression->field->value // => 'data'
 */
final class RecoveryColumn extends Expression
{
    /**
     * Derives the field facts and retains the recovery operation that produces it.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly string $scopeId, public readonly RecoveryField $field, public readonly RecoveryEncoding $encoding)
    {
        if ($scopeId === '') {
            throw new InvalidStructure('A recovery result field requires its producing operation identity.');
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, $field === RecoveryField::Data ? 'varchar' : 'bigint'), Nullability::NotNull, []), $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::XaRecovery;
    }

    /**
     * @return list<Expression> No scalar inputs; the server supplies this recovery field
     */
    #[Override]
    public function inputs(): array
    {
        return [];
    }

    /**
     * Returns the output field name, which has no standalone scalar SQL form.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->field->value;
    }

    /**
     * Preserves the facts derived from the recovery field's role.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Recovery result facts are derived from the field role.');
        }
        return new self($this->source, $this->scopeId, $this->field, $this->encoding);
    }
}
