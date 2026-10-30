<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint;
use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates WindowConstraint values from the generators of its operands.
 *
 * @implements ErisGenerator<WindowConstraint>
 */
final class WindowConstraintGenerator implements ErisGenerator
{
    /** @var ErisGenerator<WindowConstraint> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): WindowConstraint => new WindowConstraint(
                window: $parts[0]
                    ? new WindowSpec(base: $parts[1], partition: [], order: [new OrderItem(new NumberLiteral((string) (1 + $parts[2])))], frame: null)
                    : new WindowSpec(base: $parts[1], partition: [], order: [], frame: new Frame(FrameUnit::Rows, new FrameBound(FrameBoundKind::Following, new NumberLiteral((string) (1 + $parts[2]))), new FrameBound(FrameBoundKind::CurrentRow))),
                positional: $parts[0],
            ),
            Generators::tuple(Generators::bool(), Generators::oneOf(Generators::constant(null), new NameGenerator($release)), Generators::choose(0, 8))
        );
        $this->delegate = Generators::suchThat(
            static function (WindowConstraint $value) use ($release): bool {
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
     * @return GeneratedValue<WindowConstraint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<WindowConstraint> $element The generated counterexample
     * @return GeneratedValue<WindowConstraint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
