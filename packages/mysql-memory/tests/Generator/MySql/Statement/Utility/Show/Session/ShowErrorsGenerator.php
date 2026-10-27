<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Session;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowErrors values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors>
 */
final class ShowErrorsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors> */
    use Composition;

    /**
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Query\Limit>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $limit = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors(limit: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $limit ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors>>
     */
    public static function forms(): iterable
    {
        yield 'ShowErrors/96' => new ShowErrorsGenerator(
            limit: Generators::constant(null)
        );
    }

}
