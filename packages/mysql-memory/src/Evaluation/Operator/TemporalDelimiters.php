<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Context;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Reports redundant whitespace retained by runtime temporal conversion.
 *
 * Modern MySQL reports the first redundant delimiter after the conversion's
 * other warnings. Leading whitespace is reported even when conversion fails;
 * trailing or repeated separators are reported after a clock is recognized.
 * Verified through CAST and time-part functions on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class TemporalDelimiters
{
    /**
     * Records the first redundant delimiter without turning a deprecation into an error.
     */
    public function warn(string $text, bool $clock, Context $context): void
    {
        if (in_array($context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return;
        }
        $position = $this->position($text, $clock);
        if ($position === null) {
            return;
        }
        $delimiter = strtr($text[$position], ["\t" => '\\t', "\n" => '\\n', "\r" => '\\r', "\v" => '\\v', "\f" => '\\f']);
        $context->diagnostics->warning(4096, sprintf("Delimiter '%s' in position %d in datetime value '%s' at row %d is superfluous and is deprecated. Please remove.", $delimiter, $position, $text, $context->row));
    }

    /**
     * Locates leading whitespace, an extra separator or trailing whitespace.
     */
    public function position(string $text, bool $clock): ?int
    {
        if ($text !== '' && str_contains(" \t\n\r\v\f", $text[0])) {
            return 0;
        }
        if (!$clock) {
            return null;
        }
        $end = strlen(rtrim($text, " \t\n\r\v\f"));
        if (preg_match('/[ \t\n\r\v\f]{2}/', $text, $match, PREG_OFFSET_CAPTURE) === 1 && $match[0][1] < $end) {
            return $match[0][1] + 1;
        }

        return $end < strlen($text) ? $end : null;
    }
}
