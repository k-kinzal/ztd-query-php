<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint;

/**
 * Composes RollbackToSavepoint operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<RollbackToSavepoint>
 */
final class RollbackToSavepointGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RollbackToSavepoint> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $names = Generators::oneOf(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(quotes: Generators::constant(\SqlSemantics\Statement\Identifier\NameQuote::Backtick)), Generators::map(static fn (string $value): \SqlSemantics\Statement\Identifier\Name => new \SqlSemantics\Statement\Identifier\Name($value, \SqlSemantics\Statement\Identifier\NameQuote::Bare), Generators::elements('FULL', 'full', 'fUlL')));
        $this->delegate = Generators::map(static function (array $parts) use ($release): RollbackToSavepoint {
            [$name, $work, $savepointWritten] = $parts;
            return new RollbackToSavepoint($name, $work, $savepointWritten, profile: new LanguageProfile($release));
        }, \Tests\Generator\Operands::tuple($names, Generators::bool(), Generators::bool()));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<RollbackToSavepoint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RollbackToSavepoint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $savepoint The generated semantic operand
     * @param ErisGenerator<bool> $work The generated semantic operand
     * @param ErisGenerator<bool> $savepointWritten The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<RollbackToSavepoint>
     */
    public static function fromOperands(ErisGenerator $savepoint, ErisGenerator $work, ErisGenerator $savepointWritten, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): RollbackToSavepoint => new RollbackToSavepoint(savepoint: $parts[0], work: $parts[1], savepointWritten: $parts[2], profile: $parts[3], client: $parts[4]), \Tests\Generator\Operands::tuple($savepoint, $work, $savepointWritten, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<RollbackToSavepoint>>
     */
    public static function forms(): iterable
    {
        yield 'RollbackToSavepoint/407' => RollbackToSavepointGenerator::fromOperands(
            savepoint: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            work: Generators::constant(false),
            savepointWritten: Generators::constant(false),
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
