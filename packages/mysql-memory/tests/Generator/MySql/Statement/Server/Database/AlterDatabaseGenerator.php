<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
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
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord>|null $databaseWord Generated database keyword
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $options, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $databaseWord = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase(name: $parts[0], options: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], databaseWord: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $options,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $databaseWord ?? new DatabaseWordGenerator()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase>>
     */
    public static function forms(): iterable
    {
        yield 'AlterDatabase/2' => new AlterDatabaseGenerator(
            name: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new DatabaseOptionGenerator([new DatabaseReadOnlyGenerator(
                    value: Generators::constant(null)
                )])
            )
        );
        yield 'AlterDatabase/194' => new AlterDatabaseGenerator(
            name: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
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
        yield 'AlterDatabase/195' => new AlterDatabaseGenerator(
            name: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
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
        yield 'AlterDatabase/additional/58' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new AlterDatabaseGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::constant('d'),
                quote: Generators::constant(null)
            ),
            options: \Tests\Generator\Operands::tuple(
                new DatabaseOptionGenerator([new DatabaseEncryptionGenerator(
                    encryption: EncryptionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption::Disabled]),
                    spelling: Generators::constant(null)
                )])
            )
        )];
    }

}
