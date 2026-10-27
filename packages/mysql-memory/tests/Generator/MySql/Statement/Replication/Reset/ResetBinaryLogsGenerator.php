<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Reset;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ResetBinaryLogs values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs>
 */
final class ResetBinaryLogsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> $terminology Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $first Generated constructor operand
     * @param ErisGenerator<bool>|null $synonym Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $terminology, ?ErisGenerator $first = null, ?ErisGenerator $synonym = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(terminology: $parts[0], first: $parts[1], synonym: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $terminology,
            $first ?? Generators::constant(null),
            $synonym ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
