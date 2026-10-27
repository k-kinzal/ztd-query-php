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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint;

/**
 * Composes ReleaseSavepoint operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<ReleaseSavepoint>
 */
final class ReleaseSavepointGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ReleaseSavepoint> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static fn (\SqlSemantics\Statement\Identifier\Name $name): ReleaseSavepoint => new ReleaseSavepoint($name, profile: new LanguageProfile($release)), Generators::oneOf(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(quotes: Generators::constant(\SqlSemantics\Statement\Identifier\NameQuote::Backtick)), Generators::map(static fn (string $value): \SqlSemantics\Statement\Identifier\Name => new \SqlSemantics\Statement\Identifier\Name($value, \SqlSemantics\Statement\Identifier\NameQuote::Bare), Generators::elements('FULL', 'full', 'fUlL'))));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<ReleaseSavepoint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ReleaseSavepoint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $savepoint The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<ReleaseSavepoint>
     */
    public static function fromOperands(ErisGenerator $savepoint, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): ReleaseSavepoint => new ReleaseSavepoint(savepoint: $parts[0], profile: $parts[1], client: $parts[2]), \Tests\Generator\Operands::tuple($savepoint, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<ReleaseSavepoint>>
     */
    public static function forms(): iterable
    {
        yield 'ReleaseSavepoint/62' => ReleaseSavepointGenerator::fromOperands(
            savepoint: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
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
