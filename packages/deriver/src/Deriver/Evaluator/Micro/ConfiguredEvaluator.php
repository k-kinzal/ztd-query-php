<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Micro;

use Closure;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Evidence\Node;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Exception\EvaluationContractException;
use Override;
use Throwable;

/**
 * Executable micro evaluator produced from public configuration.
 * @implements MicroEvaluator<Term|null>
 * @visibility root
 */
final class ConfiguredEvaluator implements MicroEvaluator
{
    /**
     * @param Closure(Request): ?Term $evaluate Converted evaluation callback
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly string $operation,
        public readonly string $name,
        private readonly Closure $evaluate,
        public readonly int $priority = 0,
        public readonly ?string $path = null,
        public readonly ?int $start = null,
        public readonly ?int $end = null,
    ) {
    }

    /**
     * Applies configured semantics and records only the demanded dependencies.
     * @throws EvaluationContractException If trusted evaluation code fails
     */
    #[Override]
    public function evaluate(Request $request): ?Term
    {
        $operation = $request->operation();
        $source = $request->source();
        if ($operation !== $this->operation || $request->name() !== $this->name || $this->path !== null && $this->path !== $source->path || $this->start !== null && $this->start !== $source->start || $this->end !== null && $this->end !== $source->end) {
            return null;
        }
        try {
            $result = ($this->evaluate)($request);
        } catch (Throwable $error) {
            throw new EvaluationContractException($this->id . ': ' . $error->getMessage(), previous: $error);
        }
        if ($result === null) {
            return null;
        }
        $context = $request->engine->context;
        $context->evaluatorApplications++;
        $evidence = new Node(EvidenceKind::EvaluatorApplication, $context->provenance->inputs($request->inputs()), $source, ['id' => $this->id, 'version' => $this->version, 'operation' => $this->operation, 'name' => $this->name]);
        return (new \Deriver\Deriver\Evaluator\Enumeration\Choices($context->provenance))->map($result, fn (Term $value): Term => $context->provenance->attach($value, $evidence));
    }

}
