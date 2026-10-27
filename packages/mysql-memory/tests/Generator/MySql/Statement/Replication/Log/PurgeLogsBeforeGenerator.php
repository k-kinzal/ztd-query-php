<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Log;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PurgeLogsBefore values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore>
 */
final class PurgeLogsBeforeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar> $moment Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $moment, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore(moment: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $moment,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore>>
     */
    public static function forms(): iterable
    {
        yield 'PurgeLogsBefore/374' => new PurgeLogsBeforeGenerator(
            moment: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                    operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                        operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(
                            operand: new \Tests\Generator\Semantics\Statement\ScalarGenerator([new \Tests\Generator\MySql\Statement\Call\KeywordCallGenerator(
                                function: \Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User]),
                                arguments: Generators::constant([]),
                                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written])
                            )])
                        )])
                    )])
                )])
            )])
        );
    }

}
