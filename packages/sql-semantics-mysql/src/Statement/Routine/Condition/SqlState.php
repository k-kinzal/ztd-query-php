<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;

/**
 * An SQLSTATE value: `SQLSTATE [VALUE] 'xxxxx'`.
 *
 * The quoted operand and optional VALUE word remain in the request. Derivation
 * reports an invalid SQLSTATE without replacing the containing statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-condition.html.
 *
 * @visibility public
 * @example Checking the form of an SQLSTATE value
 *     $text = new \SqlSemantics\Platform\MySql\Statement\Literal\Text('45000');
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState($text))->valid() // => true
 */
final class SqlState implements Condition
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Text $state The decoded operand, written as a quoted string
     * @param \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords $valueWord Whether VALUE is written before the operand
     */
    public function __construct(public readonly Text $state, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords $valueWord = \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted)
    {
        Check::input($state->radix === null, 'An SQLSTATE value is written as a quoted string.');
    }

    /**
     * Tells whether the value has the form of an SQLSTATE that is not a success class.
     */
    public function valid(): bool
    {
        return self::accepts($this->state->value);
    }

    /**
     * Answers whether a decoded string belongs to the SQLSTATE value domain.
     */
    public static function accepts(string $value): bool
    {
        return preg_match('/\A[0-9A-Z]{5}\z/', $value) === 1 && !str_starts_with($value, '00');
    }

    /**
     * Writes SQLSTATE and the value.
     */
    public function render(Output $out): void
    {
        $out->keyword('SQLSTATE');
        if ($this->valueWord === \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written) {
            $out->keyword('VALUE');
        }
        $out->node($this->state);
    }
}
