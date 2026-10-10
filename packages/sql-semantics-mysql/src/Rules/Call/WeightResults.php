<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Rules\Typing\Texts;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;

/**
 * Resolves the binary result capacity of both WEIGHT_STRING forms.
 *
 * AS CHAR limits evaluated weights, retaining at least the operand's declared byte capacity.
 * A larger requested weight count expands that capacity before collation-specific sizing.
 * AS BINARY and a nonzero internal result length specify the capacity directly. Modern
 * releases retain a minimum of eight bytes. Legacy sizing uses the charset byte width;
 * modern collation expansion is sampled from public
 * result metadata in resources/weight-lengths.php.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_weight-string.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class WeightResults
{
    /**
     * Resolves the capacity when the operand has a precise domain.
     */
    public function fact(ScalarFact $fact, Derivation $derivation, ?WeightCast $cast = null, ?int $castLength = null, int $resultLength = 0, int $weights = 0): ScalarFact
    {
        $base = (new ResultTyping())->fact('BY', [$fact]);
        $operand = (new Precision())->domain($fact->type);
        if ($operand === null) {
            return $base;
        }
        $legacy = in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $count = $cast === WeightCast::Char ? $castLength ?? 0 : $weights;
        $length = match (true) {
            $resultLength > 0 => $resultLength,
            $cast === WeightCast::Binary => $castLength ?? 0,
            $operand->kind === Kind::String => $legacy ? max($operand->length * $operand->collation->charset->maxLength, $count) * $operand->collation->charset->maxLength : $this->length($operand, $count),
            default => max($count, (new Texts(Settings::of($derivation->context)))->length($operand)),
        };
        $minimum = $legacy ? 0 : 8;

        return new ScalarFact(new Known(Domain::string(max($minimum, $length), Collation::binary())), $base->nullability);
    }

    /**
     * Answers the declared weight capacity of a string under its collation.
     */
    public function length(Domain $operand, int $count = 0): int
    {
        return $this->capacity($operand->collation, $operand->length * $operand->collation->charset->maxLength, $count);
    }

    /**
     * Answers a weight buffer's size from its input byte capacity and requested weight count.
     */
    public function capacity(Collation $collation, int $bytes, int $count = 0): int
    {
        /** @var array<string, array{int, int}> $lengths */
        $lengths = require dirname(__DIR__, 3) . '/resources/weight-lengths.php';
        [$factor, $overhead] = $lengths[$collation->name] ?? [$collation->charset->maxLength, 0];

        return max($bytes, $count) * intdiv($factor, $collation->charset->maxLength) + $overhead;
    }
}
