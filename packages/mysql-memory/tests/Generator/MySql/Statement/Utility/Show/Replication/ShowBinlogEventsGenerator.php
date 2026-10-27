<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Replication;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowBinlogEvents values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents>
 */
final class ShowBinlogEventsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents> */
    use Composition;

    /**
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Literal\Text>|null $file Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Literal\Numeral>|null $position Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Query\Limit>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $file = null, ?ErisGenerator $position = null, ?ErisGenerator $limit = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents(file: $parts[0], position: $parts[1], limit: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $file ?? Generators::constant(null),
            $position ?? Generators::constant(null),
            $limit ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents>>
     */
    public static function forms(): iterable
    {
        yield 'ShowBinlogEvents/77' => new ShowBinlogEventsGenerator(
            file: Generators::constant(null),
            position: Generators::constant(null),
            limit: Generators::constant(null)
        );
    }

}
