<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

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
            'CONCAT' => fn (Invocation $call): ?Domain => $this->bounded($call, $sum($call), 'concat'),
            'CONCAT_WS' => fn (Invocation $call): ?Domain => $this->bounded($call, $sum($call) - $call->length($call->domain(0)) + $call->length($call->domain(0)) * max(0, count($call->domains) - 2), 'concat_ws'),
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
            'REPLACE' => fn (Invocation $call): ?Domain => $this->bounded($call, $call->length($call->domain(0)) * max(1, $call->length($call->domain(2))), 'replace'),
            'REPEAT' => static fn (Invocation $call): ?Domain => $call->text([$call->domain(0)], min(16777216, $call->length($call->domain(0)) * max(0, $call->constant(1) ?? 64)), 'repeat'),
            'LPAD' => static fn (Invocation $call): ?Domain => $call->text([$call->domain(0), $call->domain(2)], max(0, $call->constant(1) ?? $call->length($call->domain(0))), 'lpad'),
            'RPAD' => static fn (Invocation $call): ?Domain => $call->text([$call->domain(0), $call->domain(2)], max(0, $call->constant(1) ?? $call->length($call->domain(0))), 'rpad'),
            'SPACE' => static fn (Invocation $call): ?Domain => $call->text([], max(0, $call->constant(0) ?? 64), 'space'),
            'SUBSTRING_INDEX' => static fn (Invocation $call): ?Domain => $call->text([$call->domain(0), $call->domain(1)], $call->length($call->domain(0)), 'substring_index'),
            'INSERT' => static fn (Invocation $call): Domain => (new FormatResults())->inserted($call),
        ];
    }

    /**
     * Resolves CONCAT, CONCAT_WS and REPLACE with their maximum reported blob width.
     * Their long-blob metadata is capped at 16777216 bytes before result character
     * set conversion. This bound is distinct from max_allowed_packet, which limits
     * execution. Verified through SQL on MySQL 5.6 and 8.4 with different packet limits.
     */
    public function bounded(Invocation $call, int $length, string $operation): ?Domain
    {
        $domain = $call->text($call->domains, $length, $operation);
        if ($domain !== null && $domain->collation->bytes() && in_array($operation, ['concat', 'concat_ws'], true) && !in_array($call->derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            $domain = (new FormatResults())->sized($this->concatenatedBytes($call, $operation === 'concat_ws'), $domain->collation, $domain->coercibility);
        }

        return $domain !== null && $domain->field === Field::LongBlob && $domain->length > 16777216 ? Domain::string(16777216, $domain->collation, $domain->field, $domain->coercibility) : $domain;
    }

    /**
     * Computes a binary concatenation's byte bound before BLOB sizing. Text and
     * computed temporal inputs contribute their encoded byte bounds; numeric
     * inputs contribute their textual width. Verified through CONCAT and
     * CONCAT_WS on MySQL 8.0 with binary, UTF-8 and temporal operands.
     */
    public function concatenatedBytes(Invocation $call, bool $separated): int
    {
        $lengths = array_map(static fn (Domain $domain): int => $domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String || $domain->kind->temporal() ? $domain->byteLength() : $call->length($domain), $call->domains);
        $length = array_sum($lengths);
        $separator = $lengths[0] ?? 0;

        return $separated ? $length - $separator + $separator * max(0, count($lengths) - 2) : $length;
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
