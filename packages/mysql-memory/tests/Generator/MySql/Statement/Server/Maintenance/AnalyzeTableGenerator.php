<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Maintenance;

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
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Server\BinlogOptionGenerator;
use Tests\Generator\MySql\Statement\Table\TableWordGenerator;
use Tests\Support\Analysis;

/**
 * Generates AnalyzeTable values from the generators of its operands.
 *
 * @implements ErisGenerator<AnalyzeTable>
 */
final class AnalyzeTableGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AnalyzeTable> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of AnalyzeTable from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AnalyzeTable => new AnalyzeTable(binlog: $parts[0], tables: $parts[1], histogram: $parts[2], statementInformation: $parts[3], inputSource: $parts[4], tableWord: $parts[5]),
            Generators::tuple(
                Generators::oneOf(Generators::constant(null), new BinlogOptionGenerator($release)),
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new MaintainedTableGenerator($release))),
                Generators::oneOf(Generators::constant(null), new HistogramGenerator($release)),
                Generators::constant(null),
                Generators::constant(null),
                new TableWordGenerator($release)
            )
        );
        $this->delegate = Generators::map(
            static function (AnalyzeTable $statement) use ($release): AnalyzeTable {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof AnalyzeTable, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (AnalyzeTable $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (AnalyzeTable $value) use ($release): bool {
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
     * @return GeneratedValueSingle<AnalyzeTable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<AnalyzeTable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
