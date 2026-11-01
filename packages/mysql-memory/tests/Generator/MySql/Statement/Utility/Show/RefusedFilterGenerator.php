<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show;

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
use SqlSemantics\Platform\MySql\Statement\Utility\Show\RefusedFilter;
use Tests\Generator\MySql\Statement\Expression\Problem\RefusedScalarGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowFunctionStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowProcedureStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowColumnsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowDatabasesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowEventsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowKeysGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowOpenTablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTableStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTriggersGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCharacterSetGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCollationGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowVariablesGenerator;

/**
 * Generates RefusedFilter values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedFilter>
 */
final class RefusedFilterGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedFilter> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of RefusedFilter from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedFilter => new RefusedFilter(header: $parts[0], condition: $parts[1]),
            Generators::suchThat(
                static fn (array $parts): bool => ($parts[0])->filter === null,
                Generators::tuple(
                    Generators::bind(Generators::choose(0, 13), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ShowTablesGenerator($release),
                        1 => new ShowTableStatusGenerator($release),
                        2 => new ShowTriggersGenerator($release),
                        3 => new ShowColumnsGenerator($release),
                        4 => new ShowKeysGenerator($release),
                        5 => new ShowEventsGenerator($release),
                        6 => new ShowProcedureStatusGenerator($release),
                        7 => new ShowFunctionStatusGenerator($release),
                        8 => new ShowDatabasesGenerator($release),
                        9 => new ShowOpenTablesGenerator($release),
                        10 => new ShowStatusGenerator($release),
                        11 => new ShowVariablesGenerator($release),
                        12 => new ShowCharacterSetGenerator($release),
                        13 => new ShowCollationGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    new RefusedScalarGenerator($release)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedFilter $value) use ($release): bool {
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
     * @return GeneratedValueSingle<RefusedFilter>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedFilter>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
