<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Schema;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowKeys values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys>
 */
final class ShowKeysGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable> $table Generated constructor operand
     * @param ErisGenerator<bool>|null $extended Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Statement\Identifier\Name>|null $database Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere>|null $filter Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $table, ?ErisGenerator $extended = null, ?ErisGenerator $database = null, ?ErisGenerator $filter = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys(table: $parts[0], extended: $parts[1], database: $parts[2], filter: $parts[3], relationInformation: $parts[4], statementInformation: $parts[5], inputSource: $parts[6]);
        }, \Tests\Generator\Operands::tuple(
            $table,
            $extended ?? Generators::constant(false),
            $database ?? Generators::constant(null),
            $filter ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys>>
     */
    public static function forms(): iterable
    {
        yield 'ShowKeys/101' => new ShowKeysGenerator(
            table: new \Tests\Generator\MySql\Statement\Utility\Show\InspectedTableGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            ),
            extended: Generators::constant(false),
            database: Generators::constant(null),
            filter: Generators::constant(null)
        );
    }

}
