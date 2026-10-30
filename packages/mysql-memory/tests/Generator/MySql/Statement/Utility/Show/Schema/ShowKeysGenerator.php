<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Schema;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Utility\Show\InspectedTableGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\ShowWhereGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Support\Analysis;

/**
 * Generates ShowKeys values from the generators of its operands.
 *
 * @implements ErisGenerator<ShowKeys>
 */
final class ShowKeysGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ShowKeys> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ShowKeys => new ShowKeys(table: $parts[0], extended: $parts[1], database: $parts[2], filter: $parts[3], relationInformation: $parts[4], statementInformation: $parts[5], inputSource: $parts[6], listing: $parts[7], databaseIn: $parts[8], tableIn: $parts[9]),
            Generators::tuple(
                new InspectedTableGenerator($release),
                Generators::bool(),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), new ShowWhereGenerator($release, $depth - 1)) : Generators::constant(null),
                Generators::constant(null),
                Generators::constant(null),
                Generators::constant(null),
                new IndexListingGenerator($release),
                Generators::bool(),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ShowKeys $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
        $this->delegate = Generators::map(
            static fn (ShowKeys $statement): ShowKeys => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (ShowKeys $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<ShowKeys>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ShowKeys> $element The generated counterexample
     * @return GeneratedValue<ShowKeys>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
