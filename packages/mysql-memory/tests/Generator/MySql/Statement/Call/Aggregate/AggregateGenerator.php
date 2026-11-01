<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Aggregate;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Aggregates;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use Tests\Generator\MySql\Statement\Call\WindowSpecificationGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates Aggregate values from the generators of its operands.
 *
 * @implements ErisGenerator<Aggregate>
 */
final class AggregateGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Aggregate> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Aggregate from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::bind(
            Generators::tuple(new AggregateFunctionGenerator($release), Generators::bool(), Generators::bool()),
            static function (array $head) use ($release, $depth, $bit): ErisGenerator {
                [$function, $distinct, $all] = $head;
                $distinct = $distinct && $function->distinctive();
                $all = $all && !($distinct && $function === AggregateFunction::Count);
                $aliases = array_values(array_filter(AggregateAlias::cases(), static fn (AggregateAlias $alias): bool => $alias->function() === $function));
                $windowed = (new Aggregates())->limitation($function, $distinct) === null;

                return Generators::map(
                    static fn (array $parts): Aggregate => new Aggregate(function: $function, arguments: $parts[0], distinct: $distinct, all: $all, over: $windowed ? $parts[1] : null, scalarInformation: null, inputSource: null, alias: $parts[2], divisionPrecision: $function === AggregateFunction::Average ? $parts[3] : null),
                    Generators::tuple(
                        Generators::bind(
                            $function === AggregateFunction::Count ? ($distinct && !$all ? Generators::choose(1, 3) : Generators::choose($distinct ? 1 : 0, 1)) : Generators::constant(1),
                            static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ScalarGenerator($release, $depth - 1))
                        ),
                        ($bit & 508) === 0 ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), new NameGenerator($release), Generators::suchThat(static fn (WindowSpecification $window): bool => (new Constraints())->problem($window) === null, new WindowSpecificationGenerator($release, $depth - 1))),
                        $aliases === [] ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::elements($aliases)),
                        Generators::choose(0, 30)
                    )
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (Aggregate $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Aggregate>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Aggregate>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
