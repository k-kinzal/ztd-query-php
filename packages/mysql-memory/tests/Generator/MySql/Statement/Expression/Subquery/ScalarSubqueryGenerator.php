<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Subquery;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryWidth;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\DerivedOptions;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Statement\Query;
use Tests\Generator\Semantics\Statement\QueryGenerator;

/**
 * Generates ScalarSubquery values from the generators of its operands.
 *
 * @implements ErisGenerator<ScalarSubquery>
 */
final class ScalarSubqueryGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ScalarSubquery> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ScalarSubquery from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ScalarSubquery => new ScalarSubquery(query: $parts[0], scalarInformation: $parts[1], inputSource: $parts[2]),
            Generators::suchThat(
                static fn (array $parts): bool => ((new QueryWidth())->of(($parts[0])) ?? 1) === 1
                && (new ReadColumns())->problem(($parts[0])) === null
                && (new DerivedOptions())->problem(($parts[0])) === null
                && (new SubqueryOptions())->problem($parts[0]) === null,
                Generators::tuple(
                    Generators::suchThat(
                        static fn (Query $query): bool => ((new QueryWidth())->of($query) ?? 1) === 1 && (new ReadColumns())->problem($query) === null && (new DerivedOptions())->problem($query) === null && (new SubqueryOptions())->problem($query) === null,
                        new QueryGenerator($release, $depth - 1),
                        1000
                    ),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ScalarSubquery $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ScalarSubquery>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ScalarSubquery>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
