<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Selection;

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
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\MySql\Statement\Call\Window\FrameGenerator;
use Tests\Generator\MySql\Statement\Query\DirectionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredWindowSpec values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredWindowSpec>
 */
final class DeferredWindowSpecGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredWindowSpec> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredWindowSpec => new DeferredWindowSpec(base: $parts[0], partition: $parts[1], order: $parts[2] instanceof DeferredFrame ? $parts[3] : [$parts[4], ...$parts[3]], frame: $parts[2], inputSource: null),
            Generators::tuple(
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? Generators::map(static fn (DeferredOrderItem $item): DeferredOrderItem => new DeferredOrderItem($item->expression), new DeferredOrderItemGenerator($release, $depth - 1)) : Generators::map(static fn (Scalar $expression): OrderItem => new OrderItem($expression), new ScalarGenerator($release, $depth - 1))))),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? new DeferredFrameGenerator($release, $depth - 1) : new FrameGenerator($release, $depth - 1))) : Generators::constant(null),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? new DeferredOrderItemGenerator($release, $depth - 1) : Generators::map(static fn (array $item): OrderItem => new OrderItem($item[0], $item[1]), Generators::tuple(new ScalarGenerator($release, $depth - 1), Generators::oneOf(Generators::constant(null), new DirectionGenerator($release))))))),
                new DeferredOrderItemGenerator($release, $depth - 1)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredWindowSpec $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredWindowSpec>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredWindowSpec> $element The generated counterexample
     * @return GeneratedValue<DeferredWindowSpec>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
