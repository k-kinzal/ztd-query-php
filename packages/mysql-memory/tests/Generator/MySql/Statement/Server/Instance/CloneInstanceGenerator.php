<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

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
use SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\AssignmentOperatorGenerator;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Support\Analysis;

/**
 * Generates CloneInstance values from the generators of its operands.
 *
 * @implements ErisGenerator<CloneInstance>
 */
final class CloneInstanceGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CloneInstance> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CloneInstance => new CloneInstance(donor: $parts[0], port: $parts[1], password: $parts[2], directory: $parts[3], requireSsl: $parts[4], statementInformation: $parts[5], inputSource: $parts[6], operator: $parts[7]),
            Generators::tuple(
                new AccountGenerator($release),
                new NumeralGenerator($release),
                new TextGenerator($release),
                Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                Generators::oneOf(Generators::constant(null), Generators::bool()),
                Generators::constant(null),
                Generators::constant(null),
                Generators::oneOf(Generators::constant(null), new AssignmentOperatorGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (CloneInstance $value) use ($release): bool {
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
            static fn (CloneInstance $statement): CloneInstance => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (CloneInstance $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<CloneInstance>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<CloneInstance> $element The generated counterexample
     * @return GeneratedValue<CloneInstance>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
