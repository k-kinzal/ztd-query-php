<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use InvalidArgumentException;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;

/**
 * Composes Savepoint operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<Savepoint>
 */
final class SavepointGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Savepoint> */
    private ErisGenerator $delegate;

    /**
     * Generates complete Savepoint values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<Savepoint>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No Savepoint forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): Savepoint {
            if (!$statement instanceof Savepoint) {
                throw new LogicException('Publishing Savepoint must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Selects the grammar before generating operands.
     * @return ErisGenerator<Savepoint>
     */
    public static function operands(GrammarRelease $release): ErisGenerator
    {
        return Generators::map(static fn (\SqlSemantics\Statement\Identifier\Name $name): Savepoint => new Savepoint($name, profile: new LanguageProfile($release)), Generators::oneOf(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(quotes: Generators::constant(\SqlSemantics\Statement\Identifier\NameQuote::Backtick)), Generators::map(static fn (string $value): \SqlSemantics\Statement\Identifier\Name => new \SqlSemantics\Statement\Identifier\Name($value, \SqlSemantics\Statement\Identifier\NameQuote::Bare), Generators::elements('FULL', 'full', 'fUlL'))));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Savepoint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Savepoint>
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
     * @return ErisGenerator<Savepoint>
     */
    public static function fromOperands(ErisGenerator $savepoint, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): Savepoint => new Savepoint(savepoint: $parts[0], profile: $parts[1], client: $parts[2]), Generators::tuple($savepoint, $profile, $client));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<Savepoint>>
     */
    public static function forms(): iterable
    {
        yield 'Savepoint/70' => SavepointGenerator::fromOperands(
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
