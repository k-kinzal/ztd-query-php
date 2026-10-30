<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Aggregate;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\Aggregation\AggregateNesting;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Query\DirectionGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates GroupConcat values from the generators of its operands.
 *
 * @implements ErisGenerator<GroupConcat>
 */
final class GroupConcatGenerator implements ErisGenerator
{
    /** @var ErisGenerator<GroupConcat> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): GroupConcat => new GroupConcat(arguments: $parts[0], distinct: $parts[1], order: $parts[2], separator: $parts[3], over: null, scalarInformation: null, inputSource: null),
            Generators::suchThat(
                static fn (array $parts): bool => (new AggregateNesting())->problem([...$parts[0], ...$parts[2]]) === null,
                Generators::tuple(
                    Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ScalarGenerator($release, $depth - 1))),
                    Generators::bool(),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::map(static fn (array $item): OrderItem => new OrderItem($item[0], $item[1]), Generators::tuple(new ScalarGenerator($release, $depth - 1), Generators::oneOf(Generators::constant(null), new DirectionGenerator($release)))))),
                    Generators::oneOf(Generators::constant(null), new TextGenerator($release))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (GroupConcat $value) use ($release): bool {
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
     * @return GeneratedValue<GroupConcat>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<GroupConcat> $element The generated counterexample
     * @return GeneratedValue<GroupConcat>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
