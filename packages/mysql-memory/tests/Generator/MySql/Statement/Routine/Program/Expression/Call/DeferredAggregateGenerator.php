<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call;

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
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregate;
use Tests\Generator\MySql\Statement\Call\Aggregate\AggregateFunctionGenerator;
use Tests\Generator\MySql\Statement\Call\WindowSpecificationGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredAggregate values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredAggregate>
 */
final class DeferredAggregateGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredAggregate> */
    private ErisGenerator $delegate;

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

                return Generators::map(
                    static fn (array $parts): DeferredAggregate => new DeferredAggregate(function: $function, arguments: [$parts[0], ...$parts[1]], distinct: $distinct, all: $all, over: $parts[2], scalarInformation: null, inputSource: null, alias: $parts[3]),
                    Generators::tuple(
                        new DeferredOperandGenerator($release, $depth - 1),
                        $function === AggregateFunction::Count && $distinct && !$all ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ScalarGenerator($release, $depth - 1))) : Generators::constant([]),
                        ($bit & 508) === 0 ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), new NameGenerator($release), new WindowSpecificationGenerator($release, $depth - 1)),
                        $aliases === [] ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::elements($aliases))
                    )
                );
            }
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredAggregate $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredAggregate>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredAggregate> $element The generated counterexample
     * @return GeneratedValue<DeferredAggregate>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
