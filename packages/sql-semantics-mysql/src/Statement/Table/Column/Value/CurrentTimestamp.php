<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column\Value;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * A column timestamp clause, interpreted after later attributes have replaced it.
 *
 * This is a declaration operand, not a scalar clock call. Its effective use
 * must match the column's temporal type and precision; an overridden clause
 * retains its written value without becoming a scalar computation.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/timestamp-initialization.html.
 *
 * @visibility public
 * @example Keeping a superseded timestamp attribute outside Scalar
 *     $value = new \SqlSemantics\Platform\MySql\Statement\Table\Column\Value\CurrentTimestamp(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('9'));
 *     [$value instanceof \SqlSemantics\Statement\Scalar, $value->decimals()] // => [false, 9]
 */
final class CurrentTimestamp implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Numeral|null $precision The optional decimal NUM precision
     * @param OptionalWords $parentheses Whether empty parentheses are written
     */
    public function __construct(public readonly ?Numeral $precision = null, public readonly OptionalWords $parentheses = OptionalWords::Written, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($precision === null || $precision->isIntegerToken(), 'A timestamp clause precision is a decimal NUM token.');
        Check::input($precision === null || $parentheses === OptionalWords::Written, 'A timestamp clause precision is written in parentheses.');
    }

    /**
     * Answers the effective fractional precision, before column compatibility.
     */
    public function decimals(): int
    {
        return $this->precision === null ? 0 : (int) $this->precision->text % 256;
    }

    /**
     * Writes the timestamp declaration operand.
     */
    public function render(Output $out): void
    {
        $out->keyword('CURRENT_TIMESTAMP');
        if ($this->parentheses === OptionalWords::Written) {
            $out->glue()->symbol('(')->node($this->precision)->symbol(')');
        }
    }
}
