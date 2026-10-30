<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

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
use SqlSemantics\Statement\Query;
use Tests\Generator\MySql\Statement\Dml\Insert\ValuesClausesGenerator;
use Tests\Generator\MySql\Statement\Query\ExplicitTableGenerator;
use Tests\Generator\MySql\Statement\Query\ParenthesizedQueryGenerator;
use Tests\Generator\MySql\Statement\Query\QueryExpressionGenerator;
use Tests\Generator\MySql\Statement\Query\QueryStatementGenerator;
use Tests\Generator\MySql\Statement\Query\SelectGenerator;
use Tests\Generator\MySql\Statement\Query\Set\OrderedSetOperationGenerator;
use Tests\Generator\MySql\Statement\Query\Set\SetOperationGenerator;
use Tests\Generator\MySql\Statement\Query\ValuesQueryGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredBodyGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredValuesQueryGenerator;

/**
 * Generates Query values from the generators of its operands.
 *
 * @implements ErisGenerator<Query>
 */
final class QueryGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Query> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::elements(array_keys(array_filter([0, 508, 511, 511, 511, 511, 1, 511, 508, 511, 508], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new ValuesClausesGenerator($release, $depth - 1),
                1 => new ExplicitTableGenerator($release),
                2 => new ParenthesizedQueryGenerator($release, $depth - 1),
                3 => new QueryExpressionGenerator($release, $depth - 1),
                4 => new QueryStatementGenerator($release, $depth - 1),
                5 => new SelectGenerator($release, $depth - 1),
                6 => new OrderedSetOperationGenerator($release, $depth - 1),
                7 => new SetOperationGenerator($release, $depth - 1),
                8 => new ValuesQueryGenerator($release, $depth - 1),
                9 => new DeferredBodyGenerator($release, $depth - 1),
                10 => new DeferredValuesQueryGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::elements(array_keys(array_filter([508, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new ExplicitTableGenerator($release),
                1 => new SelectGenerator($release, $depth - 1),
                2 => new DeferredBodyGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (Query $value) use ($release): bool {
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
     * @return GeneratedValue<Query>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Query> $element The generated counterexample
     * @return GeneratedValue<Query>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
