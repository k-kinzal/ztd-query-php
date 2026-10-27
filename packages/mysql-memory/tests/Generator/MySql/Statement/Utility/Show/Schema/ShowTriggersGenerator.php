<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Schema;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowTriggers values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers>
 */
final class ShowTriggersGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers> */
    use Composition;

    /**
     * @param ErisGenerator<bool>|null $full Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Statement\Identifier\Name>|null $database Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike|\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere|null>|null $filter Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $full = null, ?ErisGenerator $database = null, ?ErisGenerator $filter = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers(full: $parts[0], database: $parts[1], filter: $parts[2], relationInformation: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $full ?? Generators::constant(false),
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
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers>>
     */
    public static function forms(): iterable
    {
        yield 'ShowTriggers/117' => new ShowTriggersGenerator(
            full: Generators::constant(false),
            database: Generators::constant(null),
            filter: Generators::constant(null)
        );
    }

}
