<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetTransaction;

/**
 * Composes SetTransaction operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<SetTransaction>
 */
final class SetTransactionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SetTransaction> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope|null>|null $scopes Scopes allowed in the consuming sequence
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $scopes = null)
    {
        $levels = \SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel::cases();
        $modes = \SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode::cases();
        $pairs = [];
        foreach ($levels as $level) {
            $pairs[] = [$level];
            foreach ($modes as $mode) {
                $pairs[] = [$level, $mode];
                $pairs[] = [$mode, $level];
            }
        }
        foreach ($modes as $mode) {
            $pairs[] = [$mode];
        }
        $allowed = [null, \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global, \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Session, \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Local];
        if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            $allowed[] = \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Persist;
            $allowed[] = \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::PersistOnly;
        }
        $this->delegate = Generators::map(static function (array $data) use ($release): SetTransaction {
            [$characteristics, $scope] = $data;
            return new SetTransaction($characteristics, $scope, profile: new LanguageProfile($release));
        }, \Tests\Generator\Operands::tuple(Generators::elements($pairs), $scopes ?? Generators::elements($allowed)));
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
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<array<array-key, object>> $characteristics The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope|null> $scope The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<SetTransaction>
     */
    public static function fromOperands(ErisGenerator $characteristics, ErisGenerator $scope, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): SetTransaction => new SetTransaction(characteristics: $parts[0], scope: $parts[1], profile: $parts[2], client: $parts[3]), \Tests\Generator\Operands::tuple($characteristics, $scope, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<SetTransaction>>
     */
    public static function forms(): iterable
    {
        yield 'SetTransaction/382' => SetTransactionGenerator::fromOperands(
            characteristics: \Tests\Generator\Operands::tuple(
                AccessModeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode::ReadOnly])
            ),
            scope: Generators::constant(null),
            profile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([GrammarRelease::MySql847]),
                lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                    settings: Generators::constant('')
                ),
                parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
            ),
            client: Generators::constant(null)
        );
        yield 'SetTransaction/383' => SetTransactionGenerator::fromOperands(
            characteristics: \Tests\Generator\Operands::tuple(
                AccessModeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode::ReadOnly])
            ),
            scope: \Tests\Generator\MySql\Statement\Variable\VariableScopeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global]),
            profile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([GrammarRelease::MySql847]),
                lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                    settings: Generators::constant('')
                ),
                parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
            ),
            client: Generators::constant(null)
        );
        yield 'SetTransaction/388' => SetTransactionGenerator::fromOperands(
            characteristics: \Tests\Generator\Operands::tuple(
                IsolationLevelGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel::Serializable])
            ),
            scope: Generators::constant(null),
            profile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([GrammarRelease::MySql847]),
                lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                    settings: Generators::constant('')
                ),
                parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
            ),
            client: Generators::constant(null)
        );
    }

}
