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
 * PostgreSQL's NORMALIZE: converts a string to a Unicode normal form, NFC when none is written, without evaluating it.
 * @visibility public
 * @example Inspecting the string and the normal form
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SELECT NORMALIZE('a', NFKD)");
 *     $query->outputs[0]->expression->form // => \SqlSemantics\Model\Scalar\Text\UnicodeNormalForm::Nfkd
 *     $query->outputs[0]->expression->string->spelling() // => "'a'"
 */
final class Normalization extends Expression
{
    /**
     * Derives a text result that is NULL exactly when the string is.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $string, public readonly UnicodeNormalForm $form = UnicodeNormalForm::Nfc)
    {
        if ($string->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('NORMALIZE requires PostgreSQL.');
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), $string->nullability, $string->nullExtendedBy), $source);
    }

    /**
     * Identifies a Unicode normalization.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Normalization;
    }

    /**
     * @return list<Expression> The normalized string
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->string];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'NORMALIZE';
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
            throw new InvalidStructure('Normalization facts are derived from its string.');
        }
        return new static($this->source, $this->string, $this->form);
    }
}
