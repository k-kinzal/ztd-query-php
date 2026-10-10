<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;

/**
 * Resolves the results of string functions: a string in the collation the string arguments aggregate to, as long as the longest text the function can return.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class TextResults
{
    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $same = static fn (Invocation $call): ?Domain => $call->text($call->domains, $call->length($call->domain(0)), 'string');
        $sum = static fn (Invocation $call): int => array_sum(array_map($call->length(...), $call->domains));

        return [
            'CONCAT' => static fn (Invocation $call): ?Domain => $call->text($call->domains, $sum($call), 'concat'),
            'CONCAT_WS' => static fn (Invocation $call): ?Domain => $call->text($call->domains, $sum($call) - $call->length($call->domain(0)) + $call->length($call->domain(0)) * max(0, count($call->domains) - 2), 'concat_ws'),
            'UPPER' => $same,
            'UCASE' => $same,
            'LOWER' => $same,
            'LCASE' => $same,
            'REVERSE' => $same,
            'LTRIM' => $same,
            'TRIM' => static fn (Invocation $call): ?Domain => $call->text($call->domains, $call->length($call->domain(count($call->domains) - 1)), 'trim'),
            'RTRIM' => $same,
            'LEFT' => fn (Invocation $call): ?Domain => $call->text([$call->domain(0)], $this->leading($call), 'left'),
            'RIGHT' => fn (Invocation $call): ?Domain => $call->text([$call->domain(0)], $this->leading($call), 'right'),
            'SUBSTRING' => fn (Invocation $call): ?Domain => $call->text([$call->domain(0)], $this->substring($call), 'substr'),
            'SUBSTR' => fn (Invocation $call): ?Domain => $call->text([$call->domain(0)], $this->substring($call), 'substr'),
            'MID' => fn (Invocation $call): ?Domain => $call->text([$call->domain(0)], $this->substring($call), 'substr'),
            'REPLACE' => static fn (Invocation $call): ?Domain => $call->text($call->domains, $call->length($call->domain(0)) * max(1, $call->length($call->domain(2))), 'replace'),
            'REPEAT' => static fn (Invocation $call): ?Domain => $call->text([$call->domain(0)], min(16777216, $call->length($call->domain(0)) * max(0, $call->constant(1) ?? 64)), 'repeat'),
            'LPAD' => static fn (Invocation $call): ?Domain => $call->text([$call->domain(0), $call->domain(2)], max(0, $call->constant(1) ?? $call->length($call->domain(0))), 'lpad'),
            'RPAD' => static fn (Invocation $call): ?Domain => $call->text([$call->domain(0), $call->domain(2)], max(0, $call->constant(1) ?? $call->length($call->domain(0))), 'rpad'),
            'SPACE' => static fn (Invocation $call): ?Domain => $call->text([], max(0, $call->constant(0) ?? 64), 'space'),
            'SUBSTRING_INDEX' => static fn (Invocation $call): ?Domain => $call->text([$call->domain(0), $call->domain(1)], $call->length($call->domain(0)), 'substring_index'),
            'INSERT' => static fn (Invocation $call): Domain => (new FormatResults())->inserted($call),
        ];
    }

    /**
     * Answers the length of LEFT and RIGHT: the count a literal asks for, at most the string.
     */
    public function leading(Invocation $call): int
    {
        $length = $call->length($call->domain(0));
        $count = $call->constant(1);

        return $count === null ? $length : min($length, max(0, $count));
    }

    /**
     * Answers the length of SUBSTRING: what remains after a literal position, at most a literal count.
     */
    public function substring(Invocation $call): int
    {
        $length = $call->length($call->domain(0));
        $position = $call->constant(1);
        $remaining = $position === null ? $length : max(0, $position > 0 ? $length - $position + 1 : ($position < 0 ? min($length, -$position) : 0));
        $count = count($call->domains) > 2 ? $call->constant(2) : null;

        return $count === null ? $remaining : min($remaining, max(0, $count));
    }

}
