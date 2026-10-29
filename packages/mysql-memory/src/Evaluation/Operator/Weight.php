<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * WEIGHT_STRING(value [AS CHAR(N) | AS BINARY(N)]): the bytes the server sorts a value by.
 *
 * An integer weighs eight bytes, most significant first, with the sign bit of a signed one
 * flipped, whatever AS CHAR says; AS BINARY(N) reads it as its text instead. Modern temporal
 * values have signed sortable components; legacy temporal fields use their storage width,
 * while legacy expressions use textual weights. Resolved column provenance distinguishes
 * these forms, including merged and materialized views. JSON uses its canonical text
 * under utf8mb4_bin. A double or a
 * decimal weighs NULL. A binary string, and any string AS BINARY(N), weighs its bytes, padded
 * with 0x00 or cut to N; a string of a _bin collation of utf8mb4 or utf8mb3 weighs three bytes
 * per utf8mb4 character, and two per utf8mb3 character, its code point. General Unicode and
 * single-byte latin1 weights use mappings obtained through SQL; untailored Unicode 4.0, 5.2 and 9.0 weights
 * derive from the public Unicode data. AS CHAR limits characters and
 * pads under PAD SPACE; the internal form also limits result bytes. Other collation weights
 * are not emulated (verified on a live 8.4 server). Legacy LEVEL modifiers invert the bytes
 * for DESC and reverse the weight string for REVERSE; supported collations have one level.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_weight-string.
 * https://docs.oracle.com/cd/E17952_01/mysql-5.7-en/string-functions.html#function_weight-string.
 *
 * @visibility MySqlMemory
 */
final class Weight implements Evaluable
{
    /**
     * @param Evaluable $operand The value weighed
     * @param WeightCast|null $cast The type AS casts the value to, if any
     * @param int|null $length The length of the cast
     * @param Domain $domain The domain of the weight
     * @param int $weights The internal form's requested weight count, zero for the input length
     * @param int $resultLength The internal form's result byte limit, zero for the derived capacity
     * @param int $flags The internal formatting flags
     * @param bool $descending Whether the selected level's bytes are inverted
     * @param bool $reverse Whether the selected level's weight string is reversed
     * @param bool $stored Whether the subject reads a resolved stored field
     */
    public function __construct(public readonly Evaluable $operand, public readonly ?WeightCast $cast, public readonly ?int $length, public readonly Domain $domain, public readonly int $weights = 0, public readonly int $resultLength = 0, public readonly int $flags = 0, public readonly bool $descending = false, public readonly bool $reverse = false, public readonly bool $stored = false)
    {
    }

    /**
     * Answers the domain of the weight.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Weighs the value for a row.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is of a collation whose weights are not emulated
     */
    #[Override]
    public function evaluate(Frame $frame): ?string
    {
        if ($this->cast === WeightCast::Binary) {
            return $this->binary($frame);
        }
        $value = $this->operand->evaluate($frame);
        $domain = $this->operand->domain();
        if ($value === null) {
            return null;
        }
        if ($domain->kind === Kind::Double || $domain->kind === Kind::Decimal) {
            return null;
        }
        if (in_array($domain->kind, [Kind::Integer, Kind::Year, Kind::Bit], true)) {
            if (in_array($frame->context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
                return null;
            }
            $integer = $domain->kind === Kind::Bit ? Convert::bits((string) $value) : (int) \MySqlMemory\Evaluation\ExactDecimal::raw($value);
            return pack('J', $domain->unsigned ? $integer : $integer ^ PHP_INT_MIN);
        }
        if ($domain->kind->temporal()) {
            if (!in_array($frame->context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
                return (new \MySqlMemory\Typing\TemporalWeight())->bytes((string) $value, $domain->kind, $this->stored && $domain->field === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Timestamp ? $frame->context->zone() : null);
            }
            if ($this->stored) {
                return (new \MySqlMemory\Typing\TemporalWeight())->stored((string) $value, $domain, $frame->context->zone());
            }
            $domain = Domain::string($domain->length, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('latin1_swedish_ci'));
        }
        if ($domain->kind === Kind::Json) {
            $value = Convert::toText($value, $domain);
            $domain = Domain::string($domain->length, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_bin'));
        }
        if ($domain->kind !== Kind::String) {
            throw StatementError::NotSupportedYet->error('WEIGHT_STRING of this value');
        }
        $count = $this->count($domain, $frame->context->modes->release);
        $capacity = $this->resultLength > 0 ? $this->resultLength : (new \SqlSemantics\Platform\MySql\Rules\Call\WeightResults())->capacity($domain->collation, strlen((string) $value), $count);
        if (!(new \MySqlMemory\Evaluation\Function\Strings())->fits($frame, $capacity, 'weight_string')) {
            return null;
        }
        return $this->text((string) $value, $domain, $frame->context->modes->release);
    }

    /**
     * Evaluates a binary cast without allocating a result beyond the packet limit.
     * Legacy casts interpret lengths above INT32_MAX as negative, leaving the
     * subsequent weight allocation to report the overflow for a non-NULL input.
     */
    public function binary(Frame $frame): ?string
    {
        $legacy = in_array($frame->context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $negative = $legacy && $this->length > 2147483647;
        $strings = new \MySqlMemory\Evaluation\Function\Strings();
        if (!$negative && !$strings->fits($frame, $this->length ?? 0, 'cast_as_binary')) {
            return null;
        }
        $value = $this->operand->evaluate($frame);
        if ($value === null || ($negative && !$strings->fits($frame, $this->length ?? 0, 'weight_string'))) {
            return null;
        }

        return (new Conversion($this->operand, $this->domain, $this->length, 'BINARY'))->text((string) Convert::toText($value, $this->operand->domain()), $frame->context);
    }

    /**
     * Answers the weight count, including the 5.6 character-capacity narrowing.
     */
    public function count(Domain $domain, \SqlSemantics\Contract\GrammarRelease $release): int
    {
        $count = $this->cast === WeightCast::Char ? $this->length ?? 0 : $this->weights;
        if ($this->cast === WeightCast::Char && $release === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            $width = $domain->collation->charset->maxLength;
            $count = intdiv(($count * $width) % 4294967296, $width);
        }

        return $count;
    }

    /**
     * Weighs a string with the cast, count, byte limit and padding of this call.
     */
    public function text(string $value, Domain $domain, \SqlSemantics\Contract\GrammarRelease $release): string
    {
        $legacy = in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $count = $this->count($domain, $release);
        if ($this->cast === WeightCast::Char && $this->length !== 0 && $count === 0) {
            return '';
        }
        $count = $this->resultLength > 0 ? min($count, $this->resultLength) : $count;
        $flags = $this->flags | ($legacy && ($this->cast === WeightCast::Char || ($this->flags & 64) !== 0) ? 128 : 0);
        $pad = !$legacy || $this->cast === WeightCast::Char || ($this->flags & 64) !== 0;
        $weigher = new \MySqlMemory\Typing\Weights();
        $width = $weigher->legacyWidth($domain->collation);
        if ($legacy && $width !== null) {
            $weight = $weigher->text($value, $domain->collation);
            if ($count > 0) {
                $length = $count * $width;
                $weight = substr($weight, 0, $length);
                $weight = $pad ? str_pad($weight, $length, $weigher->character(' ', $domain->collation)) : $weight;
            }
        } else {
            $weight = $weigher->text($value, $domain->collation, $count, $flags, $pad);
        }

        $weight = $this->pad($weight, $domain, $count, strlen($value));
        $weight = $this->resultLength > 0 ? substr($weight, 0, $this->resultLength) : $weight;
        $weight = $this->descending ? ~$weight : $weight;

        return $this->reverse ? strrev($weight) : $weight;
    }

    /**
     * Pads a weight to its checked result capacity when the internal form requests it.
     */
    public function pad(string $weight, Domain $domain, int $count, int $inputBytes): string
    {
        if (($this->flags & 128) === 0) {
            return $weight;
        }
        $capacity = $this->resultLength > 0 ? $this->resultLength : (new \SqlSemantics\Platform\MySql\Rules\Call\WeightResults())->capacity($domain->collation, $inputBytes, $count);
        $padding = $domain->collation->bytes() || !$domain->collation->padSpace ? "\0" : (new \MySqlMemory\Typing\Weights())->character(' ', $domain->collation);

        return str_pad($weight, $capacity, $padding);
    }

    /**
     * Answers bytes padded with 0x00 or cut to the length of the cast.
     */
    public function bytes(string $bytes): string
    {
        $length = $this->length ?? strlen($bytes);

        return str_pad(substr($bytes, 0, $length), $length, "\0");
    }
}
