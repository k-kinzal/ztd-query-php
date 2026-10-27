<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Schema;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowEvents values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents>
 */
final class ShowEventsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents> */
    use Composition;

    /**
     * @param ErisGenerator<?\SqlSemantics\Statement\Identifier\Name>|null $database Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike|\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere|null>|null $filter Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $database = null, ?ErisGenerator $filter = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents(database: $parts[0], filter: $parts[1], relationInformation: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
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
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents>>
     */
    public static function forms(): iterable
    {
        yield 'ShowEvents/97' => new ShowEventsGenerator(
            database: Generators::constant(null),
            filter: Generators::constant(null)
        );
    }

}
