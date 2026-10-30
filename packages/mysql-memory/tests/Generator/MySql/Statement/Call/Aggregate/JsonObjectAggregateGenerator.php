<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Aggregate;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\Aggregation\AggregateNesting;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use Tests\Generator\MySql\Statement\Call\WindowSpecificationGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates JsonObjectAggregate values from the generators of its operands.
 *
 * @implements ErisGenerator<JsonObjectAggregate>
 */
final class JsonObjectAggregateGenerator implements ErisGenerator
{
    /** @var ErisGenerator<JsonObjectAggregate> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): JsonObjectAggregate => new JsonObjectAggregate(key: $parts[0], value: $parts[1], keyAll: $parts[2], valueAll: $parts[3], over: $parts[4], scalarInformation: $parts[5], inputSource: $parts[6]),
            Generators::suchThat(
                static fn (array $parts): bool => (new Constraints())->problem(($parts[4])) === null && (new AggregateNesting())->problem([($parts[0]), ($parts[1])], ($parts[4]) !== null) === null,
                Generators::tuple(
                    new ScalarGenerator($release, $depth - 1),
                    new ScalarGenerator($release, $depth - 1),
                    Generators::bool(),
                    Generators::bool(),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::elements(array_keys(array_filter([511, 508], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new NameGenerator($release),
                        1 => new WindowSpecificationGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    })) : Generators::constant(null),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (JsonObjectAggregate $value) use ($release): bool {
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
     * @return GeneratedValue<JsonObjectAggregate>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<JsonObjectAggregate> $element The generated counterexample
     * @return GeneratedValue<JsonObjectAggregate>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
