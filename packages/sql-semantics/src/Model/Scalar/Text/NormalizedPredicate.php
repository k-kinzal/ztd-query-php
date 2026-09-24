<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Text;

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
 * PostgreSQL's `IS [NOT] [form] NORMALIZED`: tests whether a string is in a Unicode normal form, NFC when none is written.
 * @visibility public
 * @example Inspecting a negated normalization test
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SELECT 'a' IS NOT NFKC NORMALIZED");
 *     $query->outputs[0]->expression->form // => \SqlSemantics\Model\Scalar\Text\UnicodeNormalForm::Nfkc
 *     $query->outputs[0]->expression->negated // => true
 */
final class NormalizedPredicate extends Expression
{
    /**
     * Derives a Boolean result that is NULL exactly when the string is.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $string, public readonly UnicodeNormalForm $form, public readonly bool $negated)
    {
        if ($string->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('IS NORMALIZED requires PostgreSQL.');
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), $string->nullability, $string->nullExtendedBy), $source);
    }

    /**
     * Identifies a Unicode normalization test.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::NormalizedPredicate;
    }

    /**
     * @return list<Expression> The tested string
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->string];
    }

    /**
     * Returns the predicate as written after its operand.
     */
    #[Override]
    public function spelling(): string
    {
        return ($this->negated ? 'IS NOT ' : 'IS ') . $this->form->value . ' NORMALIZED';
    }

    /**
     * Preserves the facts derived from the string.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Normalization test facts are derived from its string.');
        }
        return new static($this->source, $this->string, $this->form, $this->negated);
    }
}
