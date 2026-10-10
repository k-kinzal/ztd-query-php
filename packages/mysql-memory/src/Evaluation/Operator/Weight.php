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
 * flipped, whatever AS CHAR says; AS BINARY(N) reads it as its text instead. A double or a
 * decimal weighs NULL. A binary string, and any string AS BINARY(N), weighs its bytes, padded
 * with 0x00 or cut to N; a string of a _bin collation of utf8mb4 or utf8mb3 weighs three bytes
 * per utf8mb4 character, and two per utf8mb3 character, its code point. General Unicode and
 * single-byte latin1 weights use mappings obtained through SQL. AS CHAR limits characters and
 * pads under PAD SPACE; the internal form also limits result bytes. Other collation weights
 * are not emulated (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_weight-string.
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
     */
    public function __construct(public readonly Evaluable $operand, public readonly ?WeightCast $cast, public readonly ?int $length, public readonly Domain $domain, public readonly int $weights = 0, public readonly int $resultLength = 0, public readonly int $flags = 0)
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
        $value = $this->operand->evaluate($frame);
        $domain = $this->operand->domain();
        if ($value === null) {
            return null;
        }
        if ($this->cast === WeightCast::Binary) {
            return (new Conversion($this->operand, $this->domain, $this->length, 'BINARY'))->text((string) Convert::toText($value, $domain), $frame->context);
        }
        if ($domain->kind === Kind::Double || $domain->kind === Kind::Decimal) {
            return null;
        }
        if ($domain->kind === Kind::Integer) {
            if (in_array($frame->context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
                return null;
            }
            return pack('J', $domain->unsigned ? (int) $value : (int) $value ^ PHP_INT_MIN);
        }
        if ($domain->kind !== Kind::String) {
            throw StatementError::NotSupportedYet->error('WEIGHT_STRING of this value');
        }
        return $this->text((string) $value, $domain, $frame->context->modes->release);
    }

    /**
     * Weighs a string with the cast, count, byte limit and padding of this call.
     */
    public function text(string $value, Domain $domain, \SqlSemantics\Contract\GrammarRelease $release): string
    {
        $legacy = in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $count = $this->cast === WeightCast::Char ? $this->length ?? 0 : $this->weights;
        $flags = $this->flags | ($legacy && ($this->cast === WeightCast::Char || ($this->flags & 64) !== 0) ? 128 : 0);
        $weight = (new \MySqlMemory\Typing\Weights())->text($value, $domain->collation, $count, $flags, !$legacy || $this->cast === WeightCast::Char || ($this->flags & 64) !== 0);

        if (($this->flags & 128) !== 0) {
            $capacity = $this->resultLength > 0 ? $this->resultLength : (new \SqlSemantics\Platform\MySql\Rules\Call\WeightResults())->capacity($domain->collation, strlen($value), $count);
            $padding = $domain->collation->bytes() ? "\0" : (new \MySqlMemory\Typing\Weights())->character(' ', $domain->collation);
            $weight = str_pad($weight, $capacity, $padding);
        }

        return $this->resultLength > 0 ? substr($weight, 0, $this->resultLength) : $weight;
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
