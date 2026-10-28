<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Schema;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowColumns values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns>
 */
final class ShowColumnsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable> $table Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing>|null $listing Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Statement\Identifier\Name>|null $database Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike|\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere|null>|null $filter Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $tableIn The generated written alternative
     * @param ErisGenerator<bool>|null $databaseIn The generated written alternative
     * @param ErisGenerator<bool>|null $fieldsKeyword Whether FIELDS is written
     */
    public function __construct(ErisGenerator $table, ?ErisGenerator $listing = null, ?ErisGenerator $database = null, ?ErisGenerator $filter = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableIn = null, ?ErisGenerator $databaseIn = null, ?ErisGenerator $fieldsKeyword = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns(table: $parts[0], listing: $parts[1], database: $parts[2], filter: $parts[3], relationInformation: $parts[4], statementInformation: $parts[5], inputSource: $parts[6], tableIn: $parts[7], databaseIn: $parts[8] && $parts[2] !== null, fieldsKeyword: $parts[9]);
        }, \Tests\Generator\Operands::tuple(
            $table,
            $listing ?? Generators::constant(null),
            $database ?? Generators::constant(null),
            $filter ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $tableIn ?? Generators::bool(),
            $databaseIn ?? Generators::bool(),
            $fieldsKeyword ?? Generators::bool()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns>>
     */
    public static function forms(): iterable
    {
        yield 'ShowColumns/80' => new ShowColumnsGenerator(
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
            listing: Generators::constant(null),
            database: Generators::constant(null),
            filter: Generators::constant(null)
        );
        yield 'ShowColumns/365' => new ShowColumnsGenerator(
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
            listing: \Tests\Generator\MySql\Statement\Utility\Show\ShowListingGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing::Full]),
            database: Generators::constant(null),
            filter: Generators::constant(null)
        );
    }

}
