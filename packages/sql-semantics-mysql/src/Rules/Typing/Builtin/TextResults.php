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
        $sum = fn (Invocation $call): int => array_sum(array_map(fn (int $index): int => $this->concatenatedLength($call, $index), array_keys($call->domains)));

        return [
            'CONCAT' => fn (Invocation $call): ?Domain => $this->bounded($call, $sum($call), 'concat'),
            'CONCAT_WS' => fn (Invocation $call): ?Domain => $this->bounded($call, $sum($call) - $this->concatenatedLength($call, 0) + $this->concatenatedLength($call, 0) * max(0, count($call->domains) - 2), 'concat_ws'),
            'UPPER' => $same,
            'UCASE' => $same,
            'LOWER' => $same,
            'LCASE' => $same,
            'REVERSE' => $same,
            'LTRIM' => $same,
            'TRIM' => $this->trim(...),
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
     * Modern releases keep a NULL trim subject as a zero-length binary result, independently of its removal text.
     * A non-NULL subject aggregates the removal operand's collation normally.
     * Verified through literal and prepared operands on MySQL 5.6–9.1.
     */
    public function trim(Invocation $call): ?Domain
    {
        $subject = $call->domain(count($call->domains) - 1);
        if ($subject->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Null && !in_array($call->derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            return Domain::string(0, Collation::binary(), coercibility: $subject->coercibility);
        }

        return $call->text($call->domains, $call->length($subject), 'trim');
    }

    /**
     * Resolves CONCAT, CONCAT_WS and REPLACE with their maximum reported blob width.
     * Their metadata is capped at the whole characters fitting in 16777216 bytes before result character
     * set conversion. This bound is distinct from max_allowed_packet, which limits
     * execution. Verified through SQL on MySQL 5.6 and 8.4 with different packet limits.
     */
    public function bounded(Invocation $call, int $length, string $operation): ?Domain
    {
        $domain = $call->text($call->domains, $length, $operation);
        if ($domain !== null && $domain->collation->bytes() && in_array($operation, ['concat', 'concat_ws'], true) && !in_array($call->derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            $domain = (new FormatResults())->sized($this->concatenatedBytes($call, $operation === 'concat_ws'), $domain->collation, $domain->coercibility);
        }

        $domain = $domain !== null && $domain->field === Field::LongBlob && $domain->length > 16777216 ? (new FormatResults())->sized(intdiv(16777216, $domain->collation->charset->maxLength), $domain->collation, $domain->coercibility) : $domain;

        return $domain === null ? null : (new \SqlSemantics\Platform\MySql\Rules\Typing\Text\ResultBounds())->computed($domain);
    }

    /**
     * Counts a concatenation operand's characters. A user-variable read carries
     * a byte bound, so its character bound rounds down in its retained charset.
     * Grouping and unary plus preserve that read. Verified through CONCAT and
     * nested HEX on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1.
     */
    public function concatenatedLength(Invocation $call, int $index): int
    {
        $node = $call->nodes[$index] ?? null;
        while ($node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped || ($node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary && $node->operator === \SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Plus)) {
            $node = $node->operand;
        }
        $domain = $call->domain($index);

        return $node instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable && $domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String
            ? intdiv($domain->metadataLength(), $domain->collation->charset->maxLength)
            : $call->length($domain);
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
