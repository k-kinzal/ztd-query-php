<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Random\RandomRange;
use SqlSemantics\Statement\AnalyzedStatement;

/**
 * Generates the migrated INSERT result domain at the analysis/execution boundary.
 *
 * @implements ErisGenerator<AnalyzedStatement>
 */
final class AnalyzedStatementGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<AnalyzedStatement>
     */
    private ErisGenerator $delegate;

    /**
     * Selects a release while retaining the concrete SQL statement type.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release)
    {
        $this->delegate = new \Tests\Generator\MySql\Statement\Dml\Insert\InsertStatementGenerator($release);
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<AnalyzedStatement>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<AnalyzedStatement>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
