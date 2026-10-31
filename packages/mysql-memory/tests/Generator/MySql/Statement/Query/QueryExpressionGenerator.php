<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use Tests\Generator\MySql\Statement\Dml\Insert\ValuesClausesGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Generator\MySql\Statement\Query\Set\SetOperationGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredBodyGenerator;
use Tests\Generator\MySql\Statement\Query\With\DeferredValuesQueryGenerator;
use Tests\Generator\MySql\Statement\Query\With\WithGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredOrderItemGenerator;

/**
 * Generates QueryExpression values from the generators of its operands.
 *
 * @implements ErisGenerator<QueryExpression>
 */
final class QueryExpressionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<QueryExpression> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of QueryExpression from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::bind(
            Generators::elements(($bit & 508) !== 0 ? [0, 1, 2, 3, 4, 5, 7] : [2, 5]),
            static fn (int $choice): ErisGenerator => match ($choice) {
                0, 1 => Generators::map(
                    static fn (array $parts): QueryExpression => new QueryExpression(with: $parts[0], body: $parts[1], orderBy: [], limit: null, queryInformation: null, inputSource: null),
                    Generators::tuple(new WithGenerator($release, $depth - 1), $choice === 0 ? new SelectGenerator($release, $depth - 1) : new DeferredBodyGenerator($release, $depth - 1))
                ),
                default => Generators::map(
                    static fn (array $parts): QueryExpression => new QueryExpression(with: $parts[0], body: $parts[1], orderBy: $parts[0] === null && $parts[3] === null ? [$parts[2][0], ...$parts[2][1]] : $parts[2][1], limit: $parts[3], queryInformation: null, inputSource: null),
                    Generators::tuple(
                        ($bit & 508) !== 0 ? Generators::oneOf(Generators::constant(null), new WithGenerator($release, $depth - 1)) : Generators::constant(null),
                        match ($choice) {
                            2 => new SetOperationGenerator($release, $depth - 1),
                            3 => new ValuesQueryGenerator($release, $depth - 1),
                            4 => new ExplicitTableGenerator($release),
                            5 => new ParenthesizedQueryGenerator($release, $depth - 1),
                            6 => new ValuesClausesGenerator($release, $depth - 1),
                            default => new DeferredValuesQueryGenerator($release, $depth - 1),
                        },
                        Generators::tuple(
                            Generators::bind(Generators::choose(0, 1), static fn (int $item): ErisGenerator => $item === 0 ? new OrderItemGenerator($release, $depth - 1) : new DeferredOrderItemGenerator($release, $depth - 1)),
                            Generators::bind(Generators::choose(0, 2), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $item): ErisGenerator => $item === 0 ? new OrderItemGenerator($release, $depth - 1) : new DeferredOrderItemGenerator($release, $depth - 1))))
                        ),
                        Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release))
                    )
                ),
            }
        );
        $this->delegate = Generators::suchThat(
            static function (QueryExpression $value) use ($release): bool {
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
     * @return GeneratedValueSingle<QueryExpression>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<QueryExpression>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
