<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Explain;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ExplainConnection values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection>
 */
final class ExplainConnectionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $connection Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $format Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier|null>|null $modifier Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $connection, ?ErisGenerator $format = null, ?ErisGenerator $modifier = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection(connection: $parts[0], format: $parts[1], modifier: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $connection,
            $format ?? Generators::constant(null),
            $modifier ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection>>
     */
    public static function forms(): iterable
    {
        yield 'ExplainConnection/369' => new ExplainConnectionGenerator(
            connection: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                text: Generators::constant('1'),
                hexadecimal: Generators::constant(false)
            ),
            format: Generators::constant(null),
            modifier: Generators::constant(null)
        );
    }

}
