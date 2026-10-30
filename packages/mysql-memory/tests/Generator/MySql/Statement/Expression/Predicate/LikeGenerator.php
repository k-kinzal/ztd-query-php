<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Predicate;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates Like values from the generators of its operands.
 *
 * @implements ErisGenerator<Like>
 */
final class LikeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Like> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static function (array $parts): Like {
                [$operand, $pattern, $escape, $negated] = $parts;
                $precedence = new Precedence();

                return new Like(
                    operand: $precedence->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR) ? $operand : new Grouped($operand),
                    pattern: $precedence->admits($pattern, Precedence::SIMPLE_EXPR) ? $pattern : new Grouped($pattern),
                    escape: $escape === null || $precedence->admits($escape, Precedence::SIMPLE_EXPR) ? $escape : new Grouped($escape),
                    negated: $negated,
                    scalarInformation: null,
                    inputSource: null,
                );
            },
            Generators::tuple(
                new ScalarGenerator($release, $depth - 1),
                new ScalarGenerator($release, $depth - 1),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), new ScalarGenerator($release, $depth - 1)) : Generators::constant(null),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Like $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<Like>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Like> $element The generated counterexample
     * @return GeneratedValue<Like>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
