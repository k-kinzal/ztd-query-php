<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Account;

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
 * A field returned by a random-password request, identified by its producing operation and account.
 * @visibility public
 * @example Inspecting the password-generation data field
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SET PASSWORD TO RANDOM');
 *     $statement->resultColumns()[2]->expression->field->value // => 'generated password'
 */
final class GeneratedPasswordColumn extends Expression
{
    /**
     * Derives the field facts and retains the password-generation operation that produces it.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly string $scopeId, public readonly AccountName|CurrentAccount|ClientAccount $account, public readonly GeneratedPasswordField $field)
    {
        if ($scopeId === '') {
            throw new InvalidStructure('A password-generation result field requires its producing operation identity.');
        }
        parent::__construct(new ExpressionFacts($field === GeneratedPasswordField::AuthenticationFactor ? new TypeDescriptor(Dialect::MySql, new \SqlSemantics\Type\Identity\Numeric\IntegerStorage(\SqlSemantics\Type\Identity\BuiltinIdentity::BigInt, unsigned: true)) : TypeDescriptor::builtin(Dialect::MySql, 'varchar'), Nullability::NotNull, []), $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::GeneratedPassword;
    }

    /**
     * @return list<Expression> No scalar inputs; the server supplies this password-generation field
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
     * Preserves the facts derived from the password-generation field's role.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Password result facts are derived from the field role.');
        }
        return new self($this->source, $this->scopeId, $this->account, $this->field);
    }
}
