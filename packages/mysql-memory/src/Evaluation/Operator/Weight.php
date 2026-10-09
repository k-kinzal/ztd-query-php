<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use Override;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * WEIGHT_STRING(value [AS CHAR(N) | AS BINARY(N)]): the bytes the server sorts a value by.
 *
 * An integer weighs eight bytes, most significant first, with the sign bit of a signed one
 * flipped, whatever AS CHAR says; AS BINARY(N) reads it as its text instead. A double or a
 * decimal weighs NULL. A binary string, and any string AS BINARY(N), weighs its bytes, padded
 * with 0x00 or cut to N; a string of a _bin collation of utf8mb4 or utf8mb3 weighs three bytes
 * per character, its code point. The weights of the other collations are not emulated
 * (verified on a live 8.4 server).
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
     */
    public function __construct(public readonly Evaluable $operand, public readonly ?WeightCast $cast, public readonly ?int $length, public readonly Domain $domain)
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
        if ($value === null || $domain->kind === Kind::Double || $domain->kind === Kind::Decimal) {
            return null;
        }
        if ($this->cast === WeightCast::Binary) {
            return (new Conversion($this->operand, $this->domain, $this->length, 'BINARY'))->text((string) Convert::toText($value, $domain), $frame->context);
        }
        if ($domain->kind === Kind::Integer) {
            if (in_array($frame->context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
                return null;
            }
            return pack('J', $domain->unsigned ? (int) $value : (int) $value ^ PHP_INT_MIN);
        }
        if ($domain->kind !== Kind::String || $this->cast !== null) {
            throw StatementError::NotSupportedYet->error('WEIGHT_STRING of this value');
        }
        $collation = $domain->collation;
        if ($collation->charset === Charset::binary()) {
            return (string) $value;
        }
        if (!str_ends_with($collation->name, '_bin') || !in_array($collation->charset->name, ['utf8mb4', 'utf8mb3'], true)) {
            throw StatementError::NotSupportedYet->error('WEIGHT_STRING of the collation ' . $collation->name);
        }
        $weight = '';
        foreach (mb_str_split(Encoding::convert((string) $value, $collation->charset, Charset::known('utf8mb4')), 1, 'UTF-8') as $character) {
            $weight .= substr(pack('N', mb_ord($character, 'UTF-8')), 1);
        }

        return $weight;
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
