<?php

declare(strict_types=1);

namespace Deriver\Deriver;

use Deriver\Deriver\Candidate\Alternative;
use Deriver\Deriver\Candidate\CandidateStatus;
use Deriver\Deriver\Evaluator\Term;

/**
 * A concrete value or a structured residual, with every supporting derivation.
 * @example Reading a derived literal
 *     $path = tempnam(sys_get_temp_dir(), 'deriver-example-');
 *     file_put_contents($path, '<?php function f(){return 42;}');
 *     $deriver = \Deriver\Deriver::prepare([$path]);
 *     unlink($path);
 *     $deriver->derive('f#return')[0]->type === \Deriver\Deriver\Candidate\CandidateStatus::Analyzed // => true
 * @visibility public
 */
final class Candidate
{
    /**
     * Whether the value is fully analyzed or contains residual dependencies.
     */
    public readonly CandidateStatus $type;
    /**
     * The known PHP type, or mixed when no narrower type is justified.
     */
    public readonly string $type_name;
    /**
     * A concrete PHP value or the structured residual Term.
     */
    public readonly mixed $result;

    /**

     * @param non-empty-list<Alternative> $evidence OR-related derivations

     */
    public function __construct(private readonly Term $term, public readonly array $evidence)
    {
        $this->type = $term->isConcrete() ? CandidateStatus::Analyzed : CandidateStatus::Partials;
        $this->type_name = match ($term->kind) {
            Evaluator\TermKind::Constant => get_debug_type($term->literal),
            Evaluator\TermKind::Array, Evaluator\TermKind::ArraySet => 'array',
            Evaluator\TermKind::Concat => 'string',
            Evaluator\TermKind::Throwable => 'never',
            default => (string) ($term->attributes['type'] ?? 'mixed'),
        };
        $this->result = $term->isConcrete() ? $term->native() : $term;
    }

    /**
     * Retains the value and its metadata with a different set of derivations.
     * @param non-empty-list<Alternative> $evidence OR-related derivations
     */
    public function withEvidence(array $evidence): self
    {
        return new self($this->term, $evidence);
    }

}
