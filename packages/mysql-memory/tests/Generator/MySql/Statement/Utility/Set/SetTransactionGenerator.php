<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

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
use SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetTransaction;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Type\Resolved\CharsetGenerator;
use Tests\Generator\MySql\Statement\Variable\VariableScopeGenerator;
use Tests\Generator\Semantics\Contract\LanguageProfileGenerator;

/**
 * Generates SetTransaction values from the generators of its operands.
 *
 * @implements ErisGenerator<SetTransaction>
 */
final class SetTransactionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SetTransaction> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of SetTransaction from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static function (array $parts): SetTransaction {
                $characteristics = array_values(array_filter([$parts[0], $parts[1]], static fn (IsolationLevel|AccessMode|null $characteristic): bool => $characteristic !== null));

                return new SetTransaction(characteristics: $characteristics === [] ? [$parts[5]] : $characteristics, scope: $parts[2], profile: $parts[3], client: $parts[4], source: null, statementInformation: null, inputSource: null);
            },
            Generators::tuple(
                Generators::oneOf(Generators::constant(null), new IsolationLevelGenerator($release)),
                Generators::oneOf(Generators::constant(null), new AccessModeGenerator($release)),
                Generators::oneOf(Generators::constant(null), new VariableScopeGenerator($release)),
                new LanguageProfileGenerator($release),
                Generators::oneOf(Generators::constant(null), new CharsetGenerator($release)),
                new IsolationLevelGenerator($release)
            )
        );
        $this->delegate = Generators::map(
            static function (SetTransaction $statement) use ($release): SetTransaction {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof SetTransaction, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (SetTransaction $value) use ($release): bool {
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
     * @return GeneratedValueSingle<SetTransaction>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<SetTransaction>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
