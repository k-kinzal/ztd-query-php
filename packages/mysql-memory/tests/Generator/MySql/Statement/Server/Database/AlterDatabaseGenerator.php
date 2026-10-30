<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes AlterDatabase values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase>
 */
final class AlterDatabaseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase> */
    use Composition;

    /**
     * Generates complete AlterDatabase values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release->database() === 'mysql') {
                $names = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::oneOf(
                    Generators::elements(['pbt', 'information_schema', 'performance_schema', '', 'db space', '名']),
                    Generators::map(static fn (int $length): string => str_repeat('d', $length), Generators::choose(1, 70)),
                    Generators::map(static fn (int $value): string => 'database_' . $value, Generators::choose(0, 10000))
                ), new \Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator(\SqlSemantics\Statement\Identifier\NameQuote::Backtick));
                $forms[] = self::operands(Generators::oneOf(Generators::constant(null), $names), DatabaseOptionGenerator::sequence($release, true));
            }

            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No AlterDatabase forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase) {
                throw new LogicException('Publishing AlterDatabase must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord>|null $databaseWord Generated database keyword
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase>
     */
    public static function operands(ErisGenerator $name, ErisGenerator $options, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $databaseWord = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase(name: $parts[0], options: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], databaseWord: $parts[4]);
        }, Generators::tuple($name, $options, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $databaseWord ?? new DatabaseWordGenerator()));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase>>
     */
    public static function forms(): iterable
    {
        yield 'AlterDatabase/2' => AlterDatabaseGenerator::operands(
            name: Generators::constant(null),
            options: Generators::tuple(
                new DatabaseOptionGenerator([new DatabaseReadOnlyGenerator(
                    value: Generators::constant(null)
                )])
            )
        );
        yield 'AlterDatabase/194' => AlterDatabaseGenerator::operands(
            name: Generators::constant(null),
            options: Generators::tuple(
                new DatabaseOptionGenerator([new DatabaseCollationGenerator(
                    collation: new \Tests\Generator\MySql\Statement\Name\CollationNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('binary'),
                            quote: Generators::constant(null)
                        )
                    )
                )])
            )
        );
        yield 'AlterDatabase/195' => AlterDatabaseGenerator::operands(
            name: Generators::constant(null),
            options: Generators::tuple(
                new DatabaseOptionGenerator([new DatabaseCharsetGenerator(
                    charset: new \Tests\Generator\MySql\Statement\Name\CharsetNameGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('binary'),
                            quote: Generators::constant(null)
                        )
                    )
                )])
            )
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'AlterDatabase/additional/58' => [\SqlSemantics\Contract\GrammarRelease::MySql847, AlterDatabaseGenerator::operands(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('d'),
                quote: Generators::constant(null)
            ),
            options: Generators::tuple(
                new DatabaseOptionGenerator([new DatabaseEncryptionGenerator(
                    encryption: EncryptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption::Disabled]),
                    spelling: Generators::constant(null)
                )])
            )
        )];
    }

}
