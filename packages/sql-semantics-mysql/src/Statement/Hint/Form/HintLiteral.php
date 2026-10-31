<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Form;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * The value a SET_VAR hint assigns: its kind and its text.
 *
 * An integer is kept without leading zeros, a decimal as written, a word
 * and a string by the characters they hold. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-set-var.
 *
 * @visibility public
 * @example Writing a value
 *     [(new \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral(\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind::Integer, '16'))->text(), (new \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral(\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind::Text, "it's"))->text()] // => ['16', "'it''s'"]
 */
final class HintLiteral
{
    use Snapshot;

    /**
     * @param HintLiteralKind $kind How the value is written
     * @param string $value The digits of a number, or the characters of a word or a string
     */
    public function __construct(public readonly HintLiteralKind $kind, public readonly string $value)
    {
        Check::input(match ($kind) {
            HintLiteralKind::Integer => preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) === 1,
            HintLiteralKind::Decimal => preg_match('/\A[0-9]*\.[0-9]+\z/', $value) === 1,
            HintLiteralKind::Word, HintLiteralKind::Text => $value !== '',
        }, 'A value of a hint is a number, a word or a string that is not empty.');
    }

    /**
     * Answers the value as a hint comment writes it: a number as is, a word between backticks and a string between quotes.
     */
    public function text(): string
    {
        return match ($this->kind) {
            HintLiteralKind::Integer, HintLiteralKind::Decimal => $this->value,
            HintLiteralKind::Word => HintTable::quote($this->value),
            HintLiteralKind::Text => "'" . str_replace("'", "''", $this->value) . "'",
        };
    }
}
