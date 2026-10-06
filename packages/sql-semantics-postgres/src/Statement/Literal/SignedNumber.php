<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A number with an optional minus sign, written where the grammar reads `NumericOnly` instead of an expression.
 *
 * Sequence options, storage parameters and configuration values take such
 * numbers. A written plus sign changes nothing and is not kept. The grammar
 * makes an integer node of a negated integer constant, and a float node of a
 * negated numeric constant whose text is the constant's text after a minus
 * sign (`doNegateFloat`); commands that read the value as text receive that.
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html,
 * `NumericOnly` and `SignedIconst` in `src/backend/parser/gram.y` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading a negative option value
 *     $number = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(true, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('5'));
 *     [$number->negative, $number->magnitude->digits] // => [true, '5']
 * @example Reading the text a command receives from a negative numeric constant
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET application_name = -.5');
 *     [$operation->statement->values[0]->text(), $operation->toString()] // => ['-.5', 'SET application_name TO - .5']
 */
final class SignedNumber implements OptionArgument
{
    use Snapshot;

    /**
     * @param bool $negative Whether a minus sign is written
     * @param IntegerConstant|NumericConstant $magnitude The number after the sign
     */
    public function __construct(public readonly bool $negative, public readonly IntegerConstant|NumericConstant $magnitude)
    {
    }

    /**
     * Answers the text the server stores for the number: the integer it denotes, or the written text of a numeric constant after the sign.
     */
    public function text(): string
    {
        if ($this->magnitude instanceof NumericConstant) {
            return ($this->negative ? '-' : '') . $this->magnitude->text;
        }

        return ($this->negative && $this->magnitude->digits !== '0' ? '-' : '') . $this->magnitude->digits;
    }

    /**
     * Derives nothing: a number holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the minus sign and the number.
     */
    public function render(Output $out): void
    {
        if ($this->negative) {
            $out->symbol('-');
        }
        $out->node($this->magnitude);
    }
}
