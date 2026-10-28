<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Schema;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowCreateDatabase values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase>
 */
final class ShowCreateDatabaseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<bool>|null $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord>|null $databaseWord Generated database keyword
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $ifNotExists = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $databaseWord = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase(name: $parts[0], ifNotExists: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], databaseWord: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $ifNotExists ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $databaseWord ?? new \Tests\Generator\MySql\Statement\Server\Database\DatabaseWordGenerator()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase>>
     */
    public static function forms(): iterable
    {
        yield 'ShowCreateDatabase/83' => new ShowCreateDatabaseGenerator(
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            ifNotExists: Generators::constant(false)
        );
    }

}
