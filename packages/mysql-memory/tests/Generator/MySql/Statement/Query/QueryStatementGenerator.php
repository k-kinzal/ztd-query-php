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
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use Tests\Generator\MySql\Statement\Dml\Insert\ValuesClausesGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoDumpfileGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoOutfileGenerator;
use Tests\Generator\MySql\Statement\Query\Into\IntoVariablesGenerator;
use Tests\Generator\MySql\Statement\Query\Locking\LockingClauseGenerator;
use Tests\Generator\MySql\Statement\Query\Set\SetOperationGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredBodyGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredValuesQueryGenerator;

/**
 * Generates QueryStatement values from the generators of its operands.
 *
 * @implements ErisGenerator<QueryStatement>
 */
final class QueryStatementGenerator implements ErisGenerator
{
    /** @var ErisGenerator<QueryStatement> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static function (array $parts) use ($bit): QueryStatement {
                [$query, $locking, $into, $afterLocking] = $parts;
                $locking = ($bit & 508) !== 0 ? $locking : array_slice($locking, 0, 1);
                $locking = $into === null && $locking === [] ? [$parts[4]] : $locking;

                return new QueryStatement(query: $query, locking: $locking, into: $into, intoPosition: $into === null ? null : ($afterLocking && $locking !== [] ? IntoPosition::AfterLocking : IntoPosition::AfterQuery), queryInformation: null, inputSource: null);
            },
            Generators::tuple(
                Generators::bind(Generators::elements(($bit & 508) !== 0 ? [0, 1, 2, 3, 5, 6] : [0, 3, 5]), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new SetOperationGenerator($release, $depth - 1),
                    1 => new ValuesQueryGenerator($release, $depth - 1),
                    2 => new ExplicitTableGenerator($release),
                    3 => new ParenthesizedQueryGenerator($release, $depth - 1),
                    4 => new ValuesClausesGenerator($release, $depth - 1),
                    5 => new DeferredBodyGenerator($release, $depth - 1),
                    default => new DeferredValuesQueryGenerator($release, $depth - 1),
                }),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new LockingClauseGenerator($release))),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new IntoDumpfileGenerator($release),
                    1 => new IntoOutfileGenerator($release),
                    default => new IntoVariablesGenerator($release, $depth - 1),
                })) : Generators::constant(null),
                Generators::bool(),
                new LockingClauseGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (QueryStatement $value) use ($release): bool {
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
     * @return GeneratedValue<QueryStatement>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<QueryStatement> $element The generated counterexample
     * @return GeneratedValue<QueryStatement>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
