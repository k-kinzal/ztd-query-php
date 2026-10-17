<?php

declare(strict_types=1);

namespace Deriver\Result;

use Deriver\Value\Term;

/**
 * A concrete value or a structured residual, with every supporting derivation.
 * @example Reading a derived literal
 *     $session = (new \Deriver\Session\Session(new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('a.php', '<?php function f(){return 42;}')]), new \Deriver\Project\Configuration()));
 *     $session->derive(new \Deriver\Query\ReturnQuery('f'))[0]->type === \Deriver\Result\CandidateStatus::Analyzed // => true
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
    public function __construct(public readonly Term $term, public readonly array $evidence)
    {
        $this->type = $term->isConcrete() ? CandidateStatus::Analyzed : CandidateStatus::Partials;
        $this->type_name = match ($term->kind) {
            'constant' => get_debug_type($term->literal),
            'array', 'array-set' => 'array',
            'concat' => 'string',
            'throwable' => 'never',
            default => (string) ($term->attributes['type'] ?? 'mixed'),
        };
        $this->result = $term->isConcrete() ? $term->native() : $term;
    }
}
