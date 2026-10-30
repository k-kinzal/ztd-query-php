<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator;

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
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredNullTest;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;

/**
 * Generates DeferredNullTest values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredNullTest>
 */
final class DeferredNullTestGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredNullTest> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredNullTest => new DeferredNullTest(operand: (new Precedence())->fits($parts[0], Precedence::BOOL_PRI, Precedence::BOOL_PRI) ? $parts[0] : ($parts[0] instanceof Scalar ? new Grouped($parts[0]) : ($parts[0] instanceof RowOperand ? new GroupedRow($parts[0]) : new DeferredGrouped($parts[0]))), negated: $parts[1], scalarInformation: $parts[2], inputSource: $parts[3]),
            Generators::tuple(
                new DeferredOperandGenerator($release, $depth - 1),
                Generators::bool(),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredNullTest $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredNullTest>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredNullTest> $element The generated counterexample
     * @return GeneratedValue<DeferredNullTest>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
