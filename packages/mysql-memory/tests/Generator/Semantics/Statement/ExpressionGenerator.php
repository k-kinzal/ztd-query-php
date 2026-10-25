<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Statement\Expression;

/**
 * Generates the migrated character-literal expression domain through Eris.
 *
 * @implements ErisGenerator<Expression>
 */
final class ExpressionGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<Expression>
     */
    private ErisGenerator $delegate;

    /**
     * Source metadata varies independently, so execution must use the expression's values.
     *
     * @param ErisGenerator<string>|null $strings The decoded values, or the general character domain
     */
    public function __construct(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation $collation, ?ErisGenerator $strings = null)
    {
        $this->delegate = new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator($collation, $strings, Generators::oneOf(Generators::constant(null), new Source\SpanGenerator()));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Expression>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Expression>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
