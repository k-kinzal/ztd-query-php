<?php

declare(strict_types=1);

namespace Deriver\Deriver;

use Closure;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Exception\InvalidInputException;

/**
 * Evaluates a selected operation using only the dependencies it requests.
 * Return null to decline, or a Term containing the concrete or partial result.
 * Change the version whenever the evaluator's meaning changes.
 * @visibility public
 * @example Supplying evaluation for an application function
 *     $evaluator = new \Deriver\Deriver\MicroEvaluator('app.answer', '1', \Deriver\Deriver\EvaluationOperation::Function, 'answer', static fn () => \Deriver\Deriver\Evaluator\Term::constant(42));
 *     $evaluator->name // => 'answer'
 */
final class MicroEvaluator
{
    /**
     * Normalized function name or exact operator name.
     */
    public readonly string $name;

    /**
     * @param Closure(Evaluation): ?Term $evaluate Trusted evaluation code; never application source
     * @throws InvalidInputException If identity or source range is invalid
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly EvaluationOperation $operation,
        string $name,
        private readonly Closure $evaluate,
        public readonly int $priority = 0,
        public readonly ?string $path = null,
        public readonly ?int $start = null,
        public readonly ?int $end = null,
    ) {
        if ($id === '' || $version === '' || $operation === EvaluationOperation::Function && ltrim($name, '\\') === '' || $start !== null && $start < 0 || $end !== null && $end < ($start ?? 0)) {
            throw new InvalidInputException('Micro evaluators require an identity, a valid operation name and a valid source range.');
        }
        $this->name = $operation === EvaluationOperation::Function ? strtolower(ltrim($name, '\\')) : $name;
    }

    /**
     * Computes a result or declines this operation without opening other inputs.
     */
    public function evaluate(Evaluation $evaluation): ?Term
    {
        return ($this->evaluate)($evaluation);
    }
}
