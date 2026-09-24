<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\StringStorage;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A MySQL `CONVERT(expr USING charset)` that re-encodes a string in another character set.
 * @visibility public
 * @example Reading the target character set
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SELECT CONVERT('abc' USING utf8mb4)");
 *     $query->outputs[0]->expression->characterSet // => 'utf8mb4'
 *     $query->toString() // => "SELECT CONVERT('abc' USING `utf8mb4`)"
 */
final class CharacterSetConversion extends Expression
{
    /**
     * Derives a string in the target character set; the `binary` character set yields a binary string.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $operand, public readonly string $characterSet)
    {
        if ($operand->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('CONVERT ... USING requires MySQL.');
        }
        if ($characterSet === '' || strtolower($characterSet) !== $characterSet) {
            throw new InvalidStructure('A character set name is nonempty and lowercase.');
        }
        $type = $characterSet === 'binary'
            ? TypeDescriptor::builtin(Dialect::MySql, 'longblob')
            : new TypeDescriptor(Dialect::MySql, new StringStorage(BuiltinIdentity::LongText, null, $characterSet));
        parent::__construct(new ExpressionFacts($type, $operand->nullability, $operand->nullExtendedBy), $source);
    }

    /**
     * Identifies a conversion.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Cast;
    }

    /**
     * @return list<Expression> The converted string
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->operand];
    }

    /**
     * Returns the conversion name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'CONVERT';
    }

    /**
     * Preserves the facts derived from the operand and the character set.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Character set conversion facts are derived from its operand.');
        }
        return new static($this->source, $this->operand, $this->characterSet);
    }
}
