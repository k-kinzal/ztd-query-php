<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Definition;

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
use SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation;
use Tests\Generator\MySql\Statement\Routine\Characteristic\CharacteristicGenerator;
use Tests\Generator\MySql\Statement\Routine\ExternalBodyGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\ProgramStatementGenerator;
use Tests\Generator\MySql\Statement\StatementGenerator;

/**
 * Generates FunctionImplementation values from the generators of its operands.
 *
 * @implements ErisGenerator<FunctionImplementation>
 */
final class FunctionImplementationGenerator implements ErisGenerator
{
    /** @var ErisGenerator<FunctionImplementation> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of FunctionImplementation from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): FunctionImplementation => new FunctionImplementation(body: $parts[0], characteristics: $parts[1]),
            Generators::suchThat(
                static fn (array $parts): bool => (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyLanguage())->compatible($parts[0], $parts[1]) && ($parts[0] instanceof \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody || (new \SqlSemantics\Platform\MySql\Rules\Routine\ReturnSearch())->found($parts[0])),
                Generators::tuple(
                    Generators::bind(Generators::elements(array_keys(array_filter([508, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ExternalBodyGenerator($release),
                        1 => new ProgramStatementGenerator($release, $depth - 1),
                        2 => new StatementGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new CharacteristicGenerator($release)))
                ),
                1000
            )
        );
        $this->delegate = Generators::suchThat(
            static function (FunctionImplementation $value) use ($release): bool {
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
     * @return GeneratedValueSingle<FunctionImplementation>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<FunctionImplementation>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
