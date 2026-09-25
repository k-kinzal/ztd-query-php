<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Evaluation;

use Override;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

/**
 * A string whose resolved parts are known but which still has gaps.
 *
 * @visibility root
 */
final class PatternTerm implements Term
{
    /**
     * @param TextPattern $pattern The partially resolved string
     */
    public function __construct(public readonly TextPattern $pattern)
    {
    }

    /**
     * The pattern itself.
     */
    #[Override]
    public function toPattern(): TextPattern
    {
        return $this->pattern;
    }

    /**
     * Always `string`, because the gaps are spliced into text.
     */
    #[Override]
    public function type(): TypeShape
    {
        return TypeShape::of(['string']);
    }

    /**
     * The pattern signature, which keeps gap origins apart.
     */
    #[Override]
    public function signature(): string
    {
        return 'pattern:' . $this->pattern->signature();
    }
}
