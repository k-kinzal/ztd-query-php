<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CreateDatabase values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase>
 */
final class CreateDatabaseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $ifNotExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $ifNotExists, ErisGenerator $name, ?ErisGenerator $options = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase(ifNotExists: $parts[0], name: $parts[1], options: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $ifNotExists,
            $name,
            $options ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase>>
     */
    public static function forms(): iterable
    {
        yield 'CreateDatabase/22' => new CreateDatabaseGenerator(
            ifNotExists: Generators::constant(false),
            name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            options: Generators::constant([])
        );
    }

}
