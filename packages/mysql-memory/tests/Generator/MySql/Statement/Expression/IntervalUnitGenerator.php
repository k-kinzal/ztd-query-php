<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

/**
 * Selects simple units, compound units and SQL_TSI aliases through Eris.
 *
 * @implements ErisGenerator<IntervalUnit>
 */
final class IntervalUnitGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<IntervalUnit>
     */
    private ErisGenerator $delegate;

    /**
     * Includes every interval keyword accepted by the semantic model.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(IntervalUnit::cases());
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<IntervalUnit>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<IntervalUnit>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
