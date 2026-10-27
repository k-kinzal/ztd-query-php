<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Identifier;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Statement\Identifier\Comparison;

/**
 * Selects a SQL name comparison through Eris.
 *
 * @implements ErisGenerator<Comparison>
 */
final class ComparisonGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<Comparison>
     */
    private ErisGenerator $delegate;

    /**
     * Includes exact and ASCII-insensitive comparison.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(Comparison::cases());
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Comparison>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Comparison>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<Comparison> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<Comparison>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

}
