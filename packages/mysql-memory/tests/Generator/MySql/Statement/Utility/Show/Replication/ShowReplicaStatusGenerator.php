<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Replication;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowReplicaStatus values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus>
 */
final class ShowReplicaStatusGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> $terminology Generated constructor operand
     * @param ErisGenerator<?\SqlSemantics\Platform\MySql\Statement\Literal\Text>|null $channel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $terminology, ?ErisGenerator $channel = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus(terminology: $parts[0], channel: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $terminology,
            $channel ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus>>
     */
    public static function forms(): iterable
    {
        yield 'ShowReplicaStatus/112' => new ShowReplicaStatusGenerator(
            terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
            channel: Generators::constant(null)
        );
    }

}
