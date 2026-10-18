<?php

declare(strict_types=1);

namespace Deriver\Deriver;

use Closure;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Sources\SourceRef;

/**
 * One operation and its lazy inputs, supplied to a public micro evaluator.
 * Function inputs use argument positions or explicit argument names. Other inputs
 * use operand positions; conditional operands are true, false, and condition.
 * @visibility public
 * @example Demanding one operand
 *     $evaluation = new \Deriver\Deriver\Evaluation(\Deriver\Deriver\EvaluationOperation::Binary, '+', new \Deriver\Deriver\Sources\SourceRef('s', 'a.php', 0, 3), static fn ($key) => \Deriver\Deriver\Evaluator\Term::constant($key));
 *     $evaluation->input(1)->native() // => 1
 */
final class Evaluation
{
    /**
     * @param Closure(int|string): Term $input Evaluates only the requested dependency
     * @param (Closure(int|string): Term)|null $truth Optional boolean demand
     */
    public function __construct(public readonly EvaluationOperation $operation, public readonly string $name, public readonly SourceRef $source, private readonly Closure $input, private readonly ?Closure $truth = null)
    {
    }

    /**
     * Evaluates an input through the ordinary dependency search.
     */
    public function input(int|string $key): Term
    {
        return ($this->input)($key);
    }

    /**
     * Requests boolean possibilities without requesting every concrete value.
     */
    public function truth(int|string $key): Term
    {
        return $this->truth === null ? (new Evaluator\Predicate\Projection())->value($this->input($key)) : ($this->truth)($key);
    }
}
