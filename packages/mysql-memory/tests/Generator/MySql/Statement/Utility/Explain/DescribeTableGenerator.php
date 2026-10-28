<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Explain;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DescribeTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable>
 */
final class DescribeTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable> $table Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainVerb>|null $verb Generated command choice
     */
    public function __construct(ErisGenerator $table, ?ErisGenerator $column = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $verb = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable(table: $parts[0], column: $parts[1], statementInformation: $parts[2], inputSource: $parts[3], verb: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $table,
            $column ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $verb ?? new ExplainVerbGenerator()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable>>
     */
    public static function forms(): iterable
    {
        yield 'DescribeTable/30' => new DescribeTableGenerator(
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
            column: Generators::constant(null)
        );
    }

}
