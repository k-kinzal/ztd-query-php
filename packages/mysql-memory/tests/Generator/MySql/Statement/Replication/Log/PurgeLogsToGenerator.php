<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Log;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes PurgeLogsTo values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo>
 */
final class PurgeLogsToGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $file Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $file, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo(file: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $file,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo>>
     */
    public static function forms(): iterable
    {
        yield 'PurgeLogsTo/61' => new PurgeLogsToGenerator(
            file: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                value: Generators::constant('text'),
                radix: Generators::constant(null)
            )
        );
    }

}
