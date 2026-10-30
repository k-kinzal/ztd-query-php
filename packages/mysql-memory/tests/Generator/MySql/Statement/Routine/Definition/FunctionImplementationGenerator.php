<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Definition;

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

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): FunctionImplementation => new FunctionImplementation(body: $parts[0], characteristics: $parts[1]),
            Generators::tuple(
                Generators::bind(Generators::elements(array_keys(array_filter([508, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ExternalBodyGenerator($release),
                    1 => new ProgramStatementGenerator($release, $depth - 1),
                    2 => new StatementGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new CharacteristicGenerator($release)))
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
     * @return GeneratedValue<FunctionImplementation>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<FunctionImplementation> $element The generated counterexample
     * @return GeneratedValue<FunctionImplementation>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
