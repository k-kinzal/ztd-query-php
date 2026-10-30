<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\GroupingGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\ProcedureAnalyseGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\WindowDefinitionGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoDumpfileGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoOutfileGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoVariablesGenerator;
use Tests\Generator\MySql\Statement\Query\OrderItemGenerator;
use Tests\Generator\MySql\Statement\Query\SelectOptionGenerator;
use Tests\Generator\MySql\Statement\Relation\DualGenerator;
use Tests\Generator\MySql\Statement\Relation\TableSourceGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredGroupingGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredOrderItemGenerator;
use Tests\Generator\Semantics\Statement\NodeGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredSelection values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredSelection>
 */
final class DeferredSelectionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredSelection> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredSelection => new DeferredSelection(items: [$parts[13], ...array_values(array_filter($parts[0], static fn ($item): bool => $item instanceof SelectExpression || $item instanceof DeferredProjection || $item instanceof TableWildcard))], from: $parts[1], where: $parts[2], options: $parts[3], groupBy: $parts[4], having: $parts[5], orderBy: $parts[6], limit: $parts[7], windows: $parts[8], procedure: $parts[1] === null ? null : $parts[9], qualify: ($bit & 480) !== 0 ? $parts[10] : null, into: $parts[11], inputSource: $parts[12]),
            Generators::tuple(
                $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new NodeGenerator($release, $depth - 1))) : Generators::constant([]),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new TableSourceGenerator($release, $depth - 1),
                    1 => new DualGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                })) : Generators::constant(null),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ScalarGenerator($release, $depth - 1),
                    1 => new RowOperandGenerator($release, $depth - 1),
                    2 => new DeferredOperandGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                })) : Generators::constant(null),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new SelectOptionGenerator($release))),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new GroupingGenerator($release, $depth - 1),
                    1 => new DeferredGroupingGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                })) : Generators::constant(null),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ScalarGenerator($release, $depth - 1),
                    1 => new RowOperandGenerator($release, $depth - 1),
                    2 => new DeferredOperandGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                })) : Generators::constant(null),
                $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new OrderItemGenerator($release, $depth - 1),
                    1 => new DeferredOrderItemGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }))) : Generators::constant([]),
                Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release)),
                $depth > 0 && ($bit & 508) !== 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new WindowDefinitionGenerator($release, $depth - 1))) : Generators::constant([]),
                ($bit & 3) !== 0 ? Generators::oneOf(Generators::constant(null), new ProcedureAnalyseGenerator($release)) : Generators::constant(null),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ScalarGenerator($release, $depth - 1),
                    1 => new RowOperandGenerator($release, $depth - 1),
                    2 => new DeferredOperandGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                })) : Generators::constant(null),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new IntoDumpfileGenerator($release),
                    1 => new IntoOutfileGenerator($release),
                    2 => new IntoVariablesGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                })) : Generators::constant(null),
                Generators::constant(null),
                new DeferredProjectionGenerator($release, $depth - 1)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredSelection $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredSelection>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredSelection> $element The generated counterexample
     * @return GeneratedValue<DeferredSelection>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
