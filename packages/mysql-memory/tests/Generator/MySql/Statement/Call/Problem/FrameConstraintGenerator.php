<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Window\FrameConstraints;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint;
use Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator;

/**
 * Generates FrameConstraint values from the generators of its operands.
 *
 * @implements ErisGenerator<FrameConstraint>
 */
final class FrameConstraintGenerator implements ErisGenerator
{
    /** @var ErisGenerator<FrameConstraint> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of FrameConstraint from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static function (array $parts): FrameConstraint {
                $condition = (new FrameConstraints())->condition($parts[0]);
                Check::invariant($condition !== null, 'The window specification carries a frame condition.');

                return new FrameConstraint(window: $parts[0], condition: $condition);
            },
            Generators::suchThat(
                static fn (array $parts): bool => (new FrameConstraints())->condition(($parts[0])) !== null,
                Generators::tuple(
                    new WindowSpecGenerator($release),
                    new FrameConditionGenerator($release)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (FrameConstraint $value) use ($release): bool {
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
     * @return GeneratedValueSingle<FrameConstraint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<FrameConstraint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
