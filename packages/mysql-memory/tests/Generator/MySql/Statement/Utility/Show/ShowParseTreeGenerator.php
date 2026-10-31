<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show;

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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\StatementGenerator;
use Tests\Support\Analysis;

/**
 * Generates ShowParseTree values from the generators of its operands.
 *
 * @implements ErisGenerator<ShowParseTree>
 */
final class ShowParseTreeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ShowParseTree> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ShowParseTree from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ShowParseTree => new ShowParseTree(statement: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]),
            Generators::suchThat(
                static fn (array $parts): bool => !($parts[0]) instanceof Begin,
                Generators::tuple(
                    new StatementGenerator($release, $depth - 1),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::map(
            static function (ShowParseTree $statement) use ($release): ShowParseTree {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof ShowParseTree, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (ShowParseTree $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (ShowParseTree $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ShowParseTree>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ShowParseTree>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
