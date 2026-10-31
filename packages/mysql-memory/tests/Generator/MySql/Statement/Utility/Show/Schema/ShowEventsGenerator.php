<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Schema;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Utility\Show\ShowLikeGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\ShowWhereGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Support\Analysis;

/**
 * Generates ShowEvents values from the generators of its operands.
 *
 * @implements ErisGenerator<ShowEvents>
 */
final class ShowEventsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ShowEvents> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ShowEvents from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ShowEvents => new ShowEvents(database: $parts[0], filter: $parts[1], relationInformation: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], databaseIn: $parts[5]),
            Generators::tuple(
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ShowLikeGenerator($release),
                    1 => new ShowWhereGenerator($release, $depth - 1),
                    default => throw new LogicException('Unreachable alternative.'),
                })) : Generators::constant(null),
                Generators::constant(null),
                Generators::constant(null),
                Generators::constant(null),
                Generators::bool()
            )
        );
        $this->delegate = Generators::map(
            static function (ShowEvents $statement) use ($release): ShowEvents {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof ShowEvents, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (ShowEvents $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (ShowEvents $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ShowEvents>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ShowEvents>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
