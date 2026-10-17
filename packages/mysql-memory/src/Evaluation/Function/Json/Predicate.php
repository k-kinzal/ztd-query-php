<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Json;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * An argument of a JSON function that is a predicate: a comparison, a logical operator, a test or TRUE or FALSE, whose value becomes a JSON boolean.
 *
 * It evaluates as the predicate does (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-creation-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Predicate implements Evaluable
{
    /**
     * @param Evaluable $predicate The compiled predicate
     */
    public function __construct(public readonly Evaluable $predicate)
    {
    }

    /**
     * Answers the domain of the predicate.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->predicate->domain();
    }

    /**
     * Evaluates the predicate.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        return $this->predicate->evaluate($frame);
    }
}
