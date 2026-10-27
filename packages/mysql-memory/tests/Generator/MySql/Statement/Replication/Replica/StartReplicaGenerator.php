<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Replica;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes StartReplica values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica>
 */
final class StartReplicaGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> $terminology Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread>>|null $threads Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil|null>|null $until Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $user Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $password Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $defaultAuth Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $pluginDir Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $channel Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $terminology, ?ErisGenerator $threads = null, ?ErisGenerator $until = null, ?ErisGenerator $user = null, ?ErisGenerator $password = null, ?ErisGenerator $defaultAuth = null, ?ErisGenerator $pluginDir = null, ?ErisGenerator $channel = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica(terminology: $parts[0], threads: $parts[1], until: $parts[2], user: $parts[3], password: $parts[4], defaultAuth: $parts[5], pluginDir: $parts[6], channel: $parts[7], statementInformation: $parts[8], inputSource: $parts[9]);
        }, \Tests\Generator\Operands::tuple(
            $terminology,
            $threads ?? Generators::constant([]),
            $until ?? Generators::constant(null),
            $user ?? Generators::constant(null),
            $password ?? Generators::constant(null),
            $defaultAuth ?? Generators::constant(null),
            $pluginDir ?? Generators::constant(null),
            $channel ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica>>
     */
    public static function forms(): iterable
    {
        yield 'StartReplica/123' => new StartReplicaGenerator(
            terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
            threads: Generators::constant([]),
            until: Generators::constant(null),
            user: Generators::constant(null),
            password: Generators::constant(null),
            defaultAuth: Generators::constant(null),
            pluginDir: Generators::constant(null),
            channel: Generators::constant(null)
        );
        yield 'StartReplica/281' => new StartReplicaGenerator(
            terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
            threads: \Tests\Generator\Operands::tuple(
                ReplicaThreadGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread::Applier])
            ),
            until: Generators::constant(null),
            user: Generators::constant(null),
            password: Generators::constant(null),
            defaultAuth: Generators::constant(null),
            pluginDir: Generators::constant(null),
            channel: Generators::constant(null)
        );
        yield 'StartReplica/282' => new StartReplicaGenerator(
            terminology: \Tests\Generator\MySql\Statement\Replication\TerminologyGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current]),
            threads: Generators::constant([]),
            until: new ReplicaUntilGenerator(
                point: UntilPointGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::AfterGaps]),
                gtids: Generators::constant(null),
                positions: Generators::constant([])
            ),
            user: Generators::constant(null),
            password: Generators::constant(null),
            defaultAuth: Generators::constant(null),
            pluginDir: Generators::constant(null),
            channel: Generators::constant(null)
        );
    }

}
