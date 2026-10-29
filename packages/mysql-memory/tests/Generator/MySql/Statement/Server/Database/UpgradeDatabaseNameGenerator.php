<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes UpgradeDatabaseName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName>
 */
final class UpgradeDatabaseNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName> */
    use Composition;

    /**
     * Generates complete UpgradeDatabaseName values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No UpgradeDatabaseName forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName) {
                throw new LogicException('Publishing UpgradeDatabaseName must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName|\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord>|null $databaseWord Generated database keyword
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName>
     */
    public static function operands(ErisGenerator $name, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $databaseWord = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName(name: $parts[0], statementInformation: $parts[1], inputSource: $parts[2], databaseWord: $parts[3]);
        }, Generators::tuple($name, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $databaseWord ?? new DatabaseWordGenerator()));
    }
    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName>}>
     */
    public static function additionalForms(): iterable
    {
        foreach ([\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744] as $release) {
            yield 'UpgradeDatabaseName/retained-name/' . $release->value => [$release, self::operands(
                name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::elements(['ordinary', '#mysql50#', '#mysql50#a/b', '#mysql50#a.b', '#mysql50#' . str_repeat('a', 65), '🦄']),
                    quote: Generators::constant(\SqlSemantics\Statement\Identifier\NameQuote::Backtick)
                )
            )];
        }
        yield 'UpgradeDatabaseName/additional/57' => [\SqlSemantics\Contract\GrammarRelease::MySql5651, UpgradeDatabaseNameGenerator::operands(
            name: new LegacyDatabaseNameGenerator(
                suffix: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::constant('🦄'),
                    quote: Generators::constant(null)
                )
            )
        )];
    }

}
