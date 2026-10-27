<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Replica;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StopReplica values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica>
 */
final class StopReplicaGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> $terminology Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread>>|null $threads Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $channel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $terminology, ?ErisGenerator $threads = null, ?ErisGenerator $channel = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica(terminology: $parts[0], threads: $parts[1], channel: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $terminology,
            $threads ?? Generators::constant([]),
            $channel ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica>>
     */
    public static function forms(): iterable
    {
        yield 'StopReplica/124' => new StopReplicaGenerator(
            terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
            threads: Generators::constant([]),
            channel: Generators::constant(null)
        );
    }

}
