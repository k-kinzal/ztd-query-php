<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query;

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
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use Tests\Generator\MySql\Statement\Hint\OptimizerHintGenerator;
use Tests\Generator\MySql\Statement\Name\TableWildcardGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\GroupingGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\LateOrderingGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\ProcedureAnalyseGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\WindowDefinitionGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoDumpfileGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoOutfileGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoPositionGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoVariablesGenerator;
use Tests\Generator\MySql\Statement\Query\Locking\LockingClauseGenerator;
use Tests\Generator\MySql\Statement\Relation\DualGenerator;
use Tests\Generator\MySql\Statement\Relation\TableSourceGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredOrderItemGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates Select values from the generators of its operands.
 *
 * @implements ErisGenerator<Select>
 */
final class SelectGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Select> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static function (array $parts) use ($bit): Select {
                [$options, $star, $items, $from, $where, $groupBy, $having, $windows, $qualify, $orderBy, $limit, $procedure, $locking, $into, $position, $late, $hints] = $parts;
                $items = $star ? [new Star(), ...$items] : $items;
                $position = $into === null ? null : match (true) {
                    $position === IntoPosition::AfterLocking && $locking !== [] => IntoPosition::AfterLocking,
                    $position === IntoPosition::AfterQuery && $from !== null => IntoPosition::AfterQuery,
                    default => IntoPosition::AfterItems,
                };
                $procedure = $from === null ? null : $procedure;
                $late = $late !== null && $into === null && $procedure === null && $orderBy === [] && $limit === null && $locking !== [] ? $late : null;

                $locking = ($bit & 508) !== 0 ? $locking : array_slice($locking, 0, 1);
                $position = $position === IntoPosition::AfterLocking && $locking === [] ? IntoPosition::AfterItems : $position;

                return new Select(options: $options, items: $items, from: $from, where: $where, groupBy: $groupBy, having: $having, windows: $windows, qualify: $qualify, orderBy: $orderBy, limit: $limit, procedure: $procedure, locking: $locking, into: $into, intoPosition: $position, queryInformation: null, inputSource: null, late: $late, hints: $hints);
            },
            Generators::tuple(
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new SelectOptionGenerator($release))),
                Generators::bool(),
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 3), static fn (int $choice): ErisGenerator => $choice === 0 ? new TableWildcardGenerator($release) : new SelectExpressionGenerator($release, $depth - 1)))),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::bool(), static fn (bool $table): ErisGenerator => $table ? new TableSourceGenerator($release, $depth - 1) : new DualGenerator($release))) : Generators::constant(null),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), new ScalarGenerator($release, $depth - 1)) : Generators::constant(null),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), new GroupingGenerator($release, $depth - 1)) : Generators::constant(null),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), new ScalarGenerator($release, $depth - 1)) : Generators::constant(null),
                $depth > 0 && ($bit & 508) !== 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new WindowDefinitionGenerator($release, $depth - 1))) : Generators::constant([]),
                $depth > 0 && ($bit & 480) !== 0 ? Generators::oneOf(Generators::constant(null), new ScalarGenerator($release, $depth - 1)) : Generators::constant(null),
                $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? new DeferredOrderItemGenerator($release, $depth - 1) : new OrderItemGenerator($release, $depth - 1)))) : Generators::constant([]),
                Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release)),
                ($bit & 3) !== 0 ? Generators::oneOf(Generators::constant(null), new ProcedureAnalyseGenerator($release)) : Generators::constant(null),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new LockingClauseGenerator($release))),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new IntoDumpfileGenerator($release), 1 => new IntoOutfileGenerator($release), default => new IntoVariablesGenerator($release, $depth - 1)
                })) : Generators::constant(null),
                new IntoPositionGenerator($release),
                $depth > 0 && ($bit & 1) !== 0 ? Generators::oneOf(Generators::constant(null), new LateOrderingGenerator($release, $depth - 1)) : Generators::constant(null),
                ($bit & 510) !== 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new OptimizerHintGenerator($release))) : Generators::constant([])
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Select $value) use ($release): bool {
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
     * @return GeneratedValue<Select>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Select> $element The generated counterexample
     * @return GeneratedValue<Select>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
