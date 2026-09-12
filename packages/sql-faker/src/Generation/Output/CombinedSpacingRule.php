<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Output;

use Override;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Lexeme\SpacingRule;

/**
 * Intersects applicable constraints without interpreting their dialect-specific conditions.
 */
final class CombinedSpacingRule implements SpacingRule
{
    /**
     * @var list<SpacingRule>
     */
    private readonly array $rules;

    /**
     * Combines boundary conditions by intersection in registration order.
     */
    public function __construct(SpacingRule ...$rules)
    {
        $this->rules = array_values($rules);
    }

    /**
     * Intersects all applicable constraints without allowing later rules to overwrite earlier restrictions.
     */
    #[Override]
    public function apply(LexemeBoundary $boundary, LexemeInput $input): SpacingConstraint
    {
        $constraint = new SpacingConstraint();
        foreach ($this->rules as $rule) {
            $applied = $rule->apply($boundary, $input);
            if ($applied !== null) {
                $constraint = $constraint->intersect($applied);
            }
        }
        return $constraint;
    }
}
