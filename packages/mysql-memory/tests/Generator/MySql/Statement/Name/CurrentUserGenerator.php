<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;

/**
 * Generates both written forms of the current account reference.
 *
 * @implements ErisGenerator<CurrentUser>
 */
final class CurrentUserGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CurrentUser> */
    private ErisGenerator $delegate;

    /**
     * Composes the optional parentheses through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (OptionalWords $words): CurrentUser => new CurrentUser($words), Generators::elements(OptionalWords::cases()));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<CurrentUser>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CurrentUser>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<OptionalWords> $parentheses The generated semantic operand
     * @return ErisGenerator<CurrentUser>
     */
    public static function fromOperands(ErisGenerator $parentheses): ErisGenerator
    {
        return Generators::map(static fn (array $parts): CurrentUser => new CurrentUser(parentheses: $parts[0]), Generators::tuple($parentheses));
    }

}
