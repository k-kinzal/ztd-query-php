<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;

/**
 * An expression whose type SQL Semantics resolved: it evaluates as compiled and reports that type.
 *
 * @visibility MySqlMemory
 */
final class Retyped implements Evaluable
{
    /**
     * @param Evaluable $evaluable The compiled expression
     * @param Domain $domain The type SQL Semantics resolved for it, with the nullability the engine derived
     */
    public function __construct(public readonly Evaluable $evaluable, public readonly Domain $domain)
    {
    }

    /**
     * Answers the type SQL Semantics resolved.
     */
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Evaluates the compiled expression.
     */
    public function evaluate(Frame $frame): int|float|string|null
    {
        return $this->evaluable->evaluate($frame);
    }
}
