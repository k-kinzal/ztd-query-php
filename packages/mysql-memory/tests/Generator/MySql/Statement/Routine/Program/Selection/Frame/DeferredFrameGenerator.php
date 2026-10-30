<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Selection\Frame;

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
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame;
use Tests\Generator\MySql\Statement\Call\Window\FrameBoundGenerator;
use Tests\Generator\MySql\Statement\Call\Window\FrameExclusionGenerator;
use Tests\Generator\MySql\Statement\Call\Window\FrameUnitGenerator;

/**
 * Generates DeferredFrame values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredFrame>
 */
final class DeferredFrameGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredFrame> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredFrame => new DeferredFrame(unit: $parts[0], start: $parts[1] ? $parts[2] : $parts[3], end: $parts[1] ? $parts[4] : $parts[2], exclusion: $parts[5], inputSource: null),
            Generators::suchThat(
                static function (array $parts): bool {
                    $start = $parts[1] ? $parts[2] : $parts[3];
                    $end = $parts[1] ? $parts[4] : $parts[2];

                    return $start->kind !== FrameBoundKind::UnboundedFollowing && ($end !== null || $start->kind !== FrameBoundKind::Following) && $end?->kind !== FrameBoundKind::UnboundedPreceding;
                },
                Generators::tuple(
                    new FrameUnitGenerator($release),
                    Generators::bool(),
                    new DeferredFrameBoundGenerator($release, $depth - 1),
                    new FrameBoundGenerator($release, $depth - 1),
                    Generators::oneOf(Generators::constant(null), Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? new DeferredFrameBoundGenerator($release, $depth - 1) : new FrameBoundGenerator($release, $depth - 1))),
                    Generators::oneOf(Generators::constant(null), new FrameExclusionGenerator($release))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredFrame $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredFrame>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredFrame> $element The generated counterexample
     * @return GeneratedValue<DeferredFrame>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
