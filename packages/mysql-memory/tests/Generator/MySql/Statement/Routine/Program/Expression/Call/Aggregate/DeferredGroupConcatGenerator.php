<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call\Aggregate;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredGroupConcat;
use Tests\Generator\MySql\Statement\Call\WindowSpecificationGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Query\DirectionGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates DeferredGroupConcat values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredGroupConcat>
 */
final class DeferredGroupConcatGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredGroupConcat> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of DeferredGroupConcat from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredGroupConcat => new DeferredGroupConcat(arguments: [new Aggregate(AggregateFunction::Count, []), ...$parts[0]], distinct: $parts[1], order: $parts[2], separator: $parts[3], over: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6]),
            Generators::tuple(
                $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ScalarGenerator($release, $depth - 1))) : Generators::constant([]),
                Generators::bool(),
                $depth > 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::map(static fn (array $item): OrderItem => new OrderItem($item[0], $item[1]), Generators::tuple(new ScalarGenerator($release, $depth - 1), Generators::oneOf(Generators::constant(null), new DirectionGenerator($release)))))) : Generators::constant([]),
                Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::elements(array_keys(array_filter([511, 508], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new NameGenerator($release),
                    1 => new WindowSpecificationGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                })) : Generators::constant(null),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredGroupConcat $value) use ($release): bool {
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
     * @return GeneratedValueSingle<DeferredGroupConcat>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<DeferredGroupConcat>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
