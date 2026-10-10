<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * The seed of RAND(N) and the sequence of doubles it starts.
 *
 * The seed is read as an integer, NULL as 0, and its low 32 bits set the two states of the
 * generator: (seed * 65537 + 55555555) and (seed * 268435457), each taken to 32 bits and then
 * modulo 2^30 - 1. Each draw sets the first state to three times itself plus the second, and the
 * second to the sum of both plus 33, both modulo 2^30 - 1, and answers the first divided by
 * 2^30 - 1. A seed known for the statement starts one sequence, kept for the statement, that
 * each evaluation advances; any other seed starts a new sequence at each evaluation. The
 * sequences match those of the server (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/mathematical-functions.html#function_rand.
 *
 * @visibility MySqlMemory
 */
final class Generator implements Evaluable
{
    /**
     * The modulus of the states.
     */
    public const MODULUS = 0x3FFFFFFF;

    /**
     * @param Evaluable $seed The argument of RAND
     * @param bool $constant Whether the seed stays the same for the statement
     */
    public function __construct(public readonly Evaluable $seed, public readonly bool $constant)
    {
    }

    /**
     * Answers the domain of the seed.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->seed->domain();
    }

    /**
     * Reads the seed.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        return $this->seed->evaluate($frame);
    }

    /**
     * Answers the next double of the sequence, starting it when the seed is read anew.
     */
    public function next(Frame $frame): float
    {
        $kept = $frame->context->kept;
        if (!$this->constant || !isset($kept[$this])) {
            $seed = $this->seed->evaluate($frame);
            $kept[$this] = $this->start($seed === null ? 0 : (new Numerics())->integer($seed, $this->seed->domain(), $frame->context));
        }
        [$first, $second] = $kept[$this];
        $first = ((int) $first * 3 + (int) $second) % self::MODULUS;
        $second = ($first + (int) $second + 33) % self::MODULUS;
        $kept[$this] = [$first, $second];

        return $first / self::MODULUS;
    }

    /**
     * Answers the two states a seed sets.
     *
     * @return list<int>
     */
    public function start(int $seed): array
    {
        $seed &= 0xFFFFFFFF;

        return [(($seed * 0x10001 + 55555555) & 0xFFFFFFFF) % self::MODULUS, (($seed * 0x10000001) & 0xFFFFFFFF) % self::MODULUS];
    }
}
