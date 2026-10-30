<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Window;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;

/**
 * Generates Frame values from the generators of its operands.
 *
 * @implements ErisGenerator<Frame>
 */
final class FrameGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Frame> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): Frame => new Frame(unit: $parts[0], start: $parts[1], end: $parts[2], exclusion: $parts[3], inputSource: $parts[4]),
            Generators::suchThat(
                static fn (array $parts): bool => ($parts[1])->kind !== FrameBoundKind::UnboundedFollowing && (($parts[2]) !== null || ($parts[1])->kind !== FrameBoundKind::Following) && ($parts[2])?->kind !== FrameBoundKind::UnboundedPreceding,
                Generators::tuple(
                    new FrameUnitGenerator($release),
                    new FrameBoundGenerator($release, $depth - 1),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), new FrameBoundGenerator($release, $depth - 1)) : Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), new FrameExclusionGenerator($release)),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Frame $value) use ($release): bool {
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
     * @return GeneratedValue<Frame>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Frame> $element The generated counterexample
     * @return GeneratedValue<Frame>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
