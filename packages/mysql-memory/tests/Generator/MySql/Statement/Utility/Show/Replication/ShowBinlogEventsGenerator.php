<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Replication;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
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
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\RowLimitGenerator;
use Tests\Support\Analysis;

/**
 * Generates ShowBinlogEvents values from the generators of its operands.
 *
 * @implements ErisGenerator<ShowBinlogEvents>
 */
final class ShowBinlogEventsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ShowBinlogEvents> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ShowBinlogEvents from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ShowBinlogEvents => new ShowBinlogEvents(file: $parts[0], position: $parts[1] === null ? null : new Numeral((string) $parts[1]), limit: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]),
            Generators::suchThat(
                static fn (array $parts): bool => ($parts[2]) === null || (($parts[2])->count instanceof NumberLiteral && (($parts[2])->offset === null || ($parts[2])->offset instanceof NumberLiteral)),
                Generators::tuple(
                    Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                    Generators::oneOf(Generators::constant(null), Generators::choose(4, 9999)),
                    Generators::oneOf(Generators::constant(null), new RowLimitGenerator($release)),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::map(
            static function (ShowBinlogEvents $statement) use ($release): ShowBinlogEvents {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof ShowBinlogEvents, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (ShowBinlogEvents $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (ShowBinlogEvents $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ShowBinlogEvents>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ShowBinlogEvents>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
