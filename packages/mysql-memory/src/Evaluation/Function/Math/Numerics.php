<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Operator\Arithmetic;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The numeric functions MOD, ATAN, ATAN2, CRC32, BIT_COUNT and RAND.
 *
 * MOD(N, M) is the operator N % M. ATAN(Y, X) and ATAN2(Y, X) are the arc tangent of Y / X in
 * the quadrant of the point (X, Y). CRC32 checks the bytes of the text of its argument, in the
 * character set of the argument. BIT_COUNT counts the bits of a binary string, and otherwise of
 * the 64-bit integer its argument reads as. RAND draws from the generator the server seeds with
 * the low 32 bits of its argument (Generator).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/mathematical-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Numerics
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('MOD', 2, 2, static fn (Frame $f, array $a, Domain $r, string $t): int|float|string|null => (new Arithmetic(ArithmeticOperator::Modulo, $a[0], $a[1], $r, $t))->evaluate($f)),
            new Routine('ATAN', 1, 2, $this->arcTangent(...)),
            new Routine('ATAN2', 1, 2, $this->arcTangent(...)),
            new Routine('CRC32', 1, 1, $this->checksum(...)),
            new Routine('BIT_COUNT', 1, 1, $this->bitCount(...)),
            new Routine('RAND', 0, 1, $this->random(...), 1, $this->seed(...), true),
        ];
    }

    /**
     * ATAN(X), ATAN(Y, X) and ATAN2(Y, X): the arc tangent, in the quadrant of the point (X, Y) with two arguments.
     *
     * @param list<Evaluable> $arguments
     */
    public function arcTangent(Frame $frame, array $arguments): ?float
    {
        $values = [];
        foreach ($arguments as $argument) {
            $value = Convert::toDouble($argument->evaluate($frame), $argument->domain(), $frame->context);
            if ($value === null) {
                return null;
            }
            $values[] = $value;
        }

        return count($values) === 1 ? atan($values[0]) : atan2($values[0], $values[1] ?? 1.0);
    }

    /**
     * CRC32(expr): the cyclic redundancy check of the bytes of the text of the argument, an unsigned 32-bit value.
     *
     * @param list<Evaluable> $arguments
     */
    public function checksum(Frame $frame, array $arguments): ?int
    {
        $text = Convert::toText($arguments[0]->evaluate($frame), $arguments[0]->domain());

        return $text === null ? null : crc32($text);
    }

    /**
     * BIT_COUNT(N): the number of bits set; a binary string counts the bits of its bytes, any other value those of the 64-bit integer it reads as.
     *
     * In MySQL 5.6 and 5.7 a binary string is read as an integer too.
     *
     * @param list<Evaluable> $arguments
     */
    public function bitCount(Frame $frame, array $arguments): ?int
    {
        $domain = $arguments[0]->domain();
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        if ($domain->kind === Kind::String && $domain->collation->bytes() && !in_array($frame->context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            $count = 0;
            foreach (count_chars((string) $value, 1) as $byte => $times) {
                $count += substr_count(decbin($byte), '1') * $times;
            }

            return $count;
        }
        return substr_count(sprintf('%064b', $this->integer($value, $domain, $frame->context)), '1');
    }

    /**
     * Reads a value as a signed 64-bit integer; a decimal beyond the range takes the nearest bound with a warning.
     */
    public function integer(int|float|string $value, Domain $domain, Context $context): int
    {
        return $domain->kind === Kind::Decimal ? Convert::decimalInteger((string) $value, $context, false) : (int) Convert::toInteger($value, $domain, $context);
    }

    /**
     * RAND() and RAND(N): a random double in [0, 1), or the next of the sequence the seed starts.
     *
     * Without a seed the value comes from the random generator of PHP. A seed known for the
     * statement starts one sequence that each evaluation advances; any other seed starts a
     * sequence for each evaluation, which answers its first value.
     *
     * @param list<Evaluable> $arguments
     */
    public function random(Frame $frame, array $arguments): float
    {
        $seed = $arguments[0] ?? null;
        if (!$seed instanceof Generator) {
            return mt_rand() / (mt_getrandmax() + 1);
        }

        return $seed->next($frame);
    }

    /**
     * Wraps the seed of RAND in the generator it starts, knowing whether it stays the same for the statement.
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known Whether each argument is known for the statement
     * @return list<Evaluable>
     */
    public function seed(Frame $frame, array $arguments, array $known): array
    {
        return $arguments === [] ? [] : [new Generator($arguments[0], $known[0] ?? false)];
    }
}
