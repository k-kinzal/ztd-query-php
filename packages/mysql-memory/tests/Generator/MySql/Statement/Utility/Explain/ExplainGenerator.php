<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Explain;

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
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\ExplainableGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates Explain values from the generators of its operands.
 *
 * @implements ErisGenerator<Explain>
 */
final class ExplainGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Explain> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Explain from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static function (array $parts) use ($bit): Explain {
                [$statement, $format, $analyze, $modifier, $into, $database, $emptyInto, $verb] = $parts;
                $analyze = ($bit & 508) !== 0 && $analyze;
                $into = ($bit & 504) !== 0 ? $into : null;
                $database = ($bit & 496) !== 0 ? $database : null;
                $emptyInto = ($bit & 48) !== 0 && $emptyInto;
                if ($modifier !== null) {
                    return new Explain(statement: $statement, format: null, analyze: false, modifier: $modifier, into: null, database: null, emptyInto: false, statementInformation: null, inputSource: null, verb: $verb);
                }
                if ($emptyInto) {
                    return new Explain(statement: $statement, format: $format, analyze: false, modifier: null, into: null, database: $database, emptyInto: true, statementInformation: null, inputSource: null, verb: $verb);
                }

                return new Explain(statement: $statement, format: $analyze && $format !== null && strcasecmp($format->value, 'TRADITIONAL') === 0 ? null : $format, analyze: $analyze, modifier: null, into: $into, database: $database, emptyInto: false, statementInformation: null, inputSource: null, verb: $verb);
            },
            Generators::tuple(
                new ExplainableGenerator($release, $depth - 1),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (string $name): Name => new Name($name), Generators::elements(($bit & 3) !== 0 ? ['TRADITIONAL', 'JSON'] : ['TRADITIONAL', 'JSON', 'TREE']))),
                Generators::bool(),
                ((new ReleaseDomain())->bit($release) & 3) !== 0 ? Generators::oneOf(Generators::constant(null), new ExplainModifierGenerator($release)) : Generators::constant(null),
                Generators::oneOf(Generators::constant(null), new UserVariableGenerator($release)),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::bool(),
                new ExplainVerbGenerator($release)
            )
        );
        $this->delegate = Generators::map(
            static function (Explain $statement) use ($release): Explain {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof Explain, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (Explain $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Explain>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Explain>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
