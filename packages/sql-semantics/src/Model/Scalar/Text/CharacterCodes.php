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
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\StringStorage;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * MySQL `CHAR(code, ... [USING charset])`: the string whose characters have the given codes, a binary string unless
 * USING names a character set; NULL codes are skipped, so the result is never NULL.
 * @visibility public
 * @example Reading the codes and the character set
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT CHAR(77, 121 USING utf8mb4)');
 *     count($query->outputs[0]->expression->codes) // => 2
 *     $query->outputs[0]->expression->characterSet // => 'utf8mb4'
 *     (new \SqlSemantics\SimpleSerializer())->serialize($query) // => 'SELECT CHAR(77, 121 USING `utf8mb4`)'
 */
final class CharacterCodes extends Expression
{
    /**
     * @var non-empty-list<Expression> Character codes in order
     */
    public readonly array $codes;

    /**
     * @param list<Expression> $codes
     * @param string|null $characterSet Lowercase character set of the result; null returns a binary string
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, array $codes, public readonly ?string $characterSet = null)
    {
        Collections::objects($codes, Expression::class);
        $this->codes = Collections::nonEmpty($codes);
        foreach ($codes as $code) {
            if ($code->type->dialect !== Dialect::MySql) {
                throw new InvalidStructure('CHAR(... USING ...) requires MySQL.');
            }
        }
        if ($characterSet !== null && ($characterSet === '' || strtolower($characterSet) !== $characterSet)) {
            throw new InvalidStructure('A character set name is nonempty and lowercase.');
        }
        $type = $characterSet === null || $characterSet === 'binary'
            ? TypeDescriptor::builtin(Dialect::MySql, 'longblob')
            : new TypeDescriptor(Dialect::MySql, new StringStorage(BuiltinIdentity::LongText, null, $characterSet));
        parent::__construct(new ExpressionFacts($type, Nullability::NotNull), $source);
    }

    /**
     * Identifies a function of its operands.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Function;
    }

    /**
     * @return list<Expression> The character codes in order
     */
    #[Override]
    public function inputs(): array
    {
        return $this->codes;
    }

    /**
     * Returns the fixed function name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'CHAR';
    }

    /**
     * Preserves the facts derived from the character set.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('CHAR facts are derived from its character set.');
        }
        return new static($this->source, $this->codes, $this->characterSet);
    }
}
