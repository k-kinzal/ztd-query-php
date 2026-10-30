<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Operator;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredLogical;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\MySql\Statement\Expression\LogicalOperatorGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredLogical values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredLogical>
 */
final class DeferredLogicalGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredLogical> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredLogical => new DeferredLogical(operator: $parts[0], left: (new Precedence())->fits($parts[1], $parts[0]->level(), $parts[0]->level()) ? $parts[1] : ($parts[1] instanceof Scalar ? new Grouped($parts[1]) : ($parts[1] instanceof RowOperand ? new GroupedRow($parts[1]) : new DeferredGrouped($parts[1]))), right: (new Precedence())->opening($parts[2]) > $parts[0]->level() ? $parts[2] : ($parts[2] instanceof Scalar ? new Grouped($parts[2]) : ($parts[2] instanceof RowOperand ? new GroupedRow($parts[2]) : new DeferredGrouped($parts[2]))), symbolic: $parts[0] !== LogicalOperator::Xor && $parts[3], scalarInformation: $parts[4], inputSource: $parts[5]),
            Generators::suchThat(
                static fn (array $parts): bool => (!((new Precedence())->fits($parts[1], $parts[0]->level(), $parts[0]->level()) ? $parts[1] : ($parts[1] instanceof Scalar ? new Grouped($parts[1]) : ($parts[1] instanceof RowOperand ? new GroupedRow($parts[1]) : new DeferredGrouped($parts[1])))) instanceof Scalar || !((new Precedence())->opening($parts[2]) > $parts[0]->level() ? $parts[2] : ($parts[2] instanceof Scalar ? new Grouped($parts[2]) : ($parts[2] instanceof RowOperand ? new GroupedRow($parts[2]) : new DeferredGrouped($parts[2])))) instanceof Scalar),
                Generators::tuple(
                    new LogicalOperatorGenerator($release),
                    Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ScalarGenerator($release, $depth - 1),
                        1 => new RowOperandGenerator($release, $depth - 1),
                        2 => new DeferredOperandGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ScalarGenerator($release, $depth - 1),
                        1 => new RowOperandGenerator($release, $depth - 1),
                        2 => new DeferredOperandGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::bool(),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredLogical $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredLogical>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredLogical> $element The generated counterexample
     * @return GeneratedValue<DeferredLogical>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
