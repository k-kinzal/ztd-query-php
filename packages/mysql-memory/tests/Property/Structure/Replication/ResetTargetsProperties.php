<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Replication;

use JsonException;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Replication\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Metadata;

/**
 * Constructor-generated log indexes execute without a parser or an input SQL string.
 */
#[CoversNothing]
#[Medium]
final class ResetTargetsProperties extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSeeds(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            yield 'reset:' . $seed => [(string) $seed];
        }
    }

    /**
     * @throws JsonException When the value inventory is invalid
     */
    #[DataProvider('providerSeeds')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesGeneratedLogIndexesExecuteWithRepeatedTargets(string $seed): void
    {
        $logs = (new Construction(new Choices('reset-index:' . $seed), new Catalogue(), new Metadata()))->object(ResetBinaryLogs::class, 4);
        self::assertInstanceOf(ResetBinaryLogs::class, $logs);
        $session = (new Instance($logs->synonym ? '8.3.0' : ($logs->terminology === Terminology::Legacy ? '8.0.44' : '8.4.7')))->connect();
        $session->text = 'unrelated invalid input';
        $statement = new Reset([new ResetReplica(Terminology::Current, true, new Text('missing')), $logs, new ResetBinaryLogs($logs->terminology), new ResetReplica(Terminology::Current)]);

        self::assertInstanceOf(Completion::class, (new Execution())->runIn($session, $statement));
        self::assertSame([$logs->first === null ? 1 : (new Magnitudes())->integer($logs->first)], $session->instance->registry->binaryLog->files);
        self::assertSame(1, $session->instance->registry->status->read('Flush_commands'));
        $deprecated = \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ResetMaster;
        self::assertSame($logs->synonym ? array_fill(0, 2, ['Warning', $deprecated->code(), $deprecated->value]) : [], $session->diagnostics->conditions);
    }
    /**
     * @throws JsonException When the value inventory is invalid
     */
    #[DataProvider('providerSeeds')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesGeneratedReplicaOptionsCanBeOverriddenWithoutInputSql(string $seed): void
    {
        $replica = (new Construction(new Choices('reset-replica:' . $seed), new Catalogue(), new Metadata()))->object(ResetReplica::class, 4);
        self::assertInstanceOf(ResetReplica::class, $replica);
        $session = (new Instance($replica->terminology === Terminology::Legacy ? '5.7.44' : '8.0.44', ['server_id' => '1']))->connect();
        $session->text = 'unrelated invalid input';
        $statement = new Reset([$replica, new ResetReplica($replica->terminology)]);

        self::assertInstanceOf(Completion::class, (new Execution())->runIn($session, $statement));
        self::assertFalse($session->instance->registry->replication->initialized);
        self::assertSame([], array_values(array_filter($session->diagnostics->conditions, static fn (array $condition): bool => $condition[0] === 'Error')));
    }

}
