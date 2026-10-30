<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Explain;

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
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;
use Tests\Support\Analysis;

/**
 * Generates ExplainConnection values from the generators of its operands.
 *
 * @implements ErisGenerator<ExplainConnection>
 */
final class ExplainConnectionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ExplainConnection> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static function (array $parts): ExplainConnection {
                [$connection, $format, $modifier, $verb, $analyze, $into, $emptyInto] = $parts;
                if ($modifier !== null) {
                    return new ExplainConnection(connection: $connection, format: null, modifier: $modifier, statementInformation: null, inputSource: null, verb: $verb, analyze: false, into: null, emptyInto: false);
                }

                return new ExplainConnection(connection: $connection, format: $format, modifier: null, statementInformation: null, inputSource: null, verb: $verb, analyze: $analyze, into: $emptyInto ? null : $into, emptyInto: $emptyInto);
            },
            Generators::tuple(
                new NumeralGenerator($release),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (string $name): Name => new Name($name), Generators::elements(($bit & 3) !== 0 ? ['TRADITIONAL', 'JSON'] : ['TRADITIONAL', 'JSON', 'TREE']))),
                ((new ReleaseDomain())->bit($release) & 3) !== 0 ? Generators::oneOf(Generators::constant(null), new ExplainModifierGenerator($release)) : Generators::constant(null),
                new ExplainVerbGenerator($release),
                Generators::bool(),
                Generators::oneOf(Generators::constant(null), new UserVariableGenerator($release)),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ExplainConnection $value) use ($release): bool {
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
            static fn (ExplainConnection $statement): ExplainConnection => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (ExplainConnection $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<ExplainConnection>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ExplainConnection> $element The generated counterexample
     * @return GeneratedValue<ExplainConnection>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
