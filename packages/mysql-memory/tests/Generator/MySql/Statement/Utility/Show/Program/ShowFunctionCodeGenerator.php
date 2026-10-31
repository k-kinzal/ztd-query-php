<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Program;

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
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionCode;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Support\Analysis;

/**
 * Generates ShowFunctionCode values from the generators of its operands.
 *
 * @implements ErisGenerator<ShowFunctionCode>
 */
final class ShowFunctionCodeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ShowFunctionCode> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ShowFunctionCode from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ShowFunctionCode => new ShowFunctionCode(name: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]),
            Generators::tuple(
                new QualifiedNameGenerator($release),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::map(
            static function (ShowFunctionCode $statement) use ($release): ShowFunctionCode {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof ShowFunctionCode, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (ShowFunctionCode $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (ShowFunctionCode $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ShowFunctionCode>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ShowFunctionCode>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
