<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\TestTrait;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use Tests\Generator\MySql\Statement\StatementGenerator;

/**
 * Executes generated replication requests before and after their channel configuration.
 */
#[CoversNothing]
#[Large]
final class ReplicationExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Complete input conditions precede channel lookup and leave configured state unchanged.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesConditionsPrecedeChannelLookupAndStateChanges(GrammarRelease $release): void
    {
        $this->forAll(StatementGenerator::replicationConditions($release))->then(static function (array $input) use ($release): void {
            [$configure, $missing, $configured, $expected] = $input;
            self::assertInstanceOf(ChangeReplicationSource::class, $configure);
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $missing);
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $configured);
            $session = (new Instance(substr($release->value, 6), ['server_id' => 1, ...($release === GrammarRelease::MySql5744 ? ['master_info_repository' => 'TABLE', 'relay_log_info_repository' => 'TABLE'] : [])], ['pbt']))->connect(database: 'pbt');
            $session->text = 'generated replication requests without SQL';
            $execution = new Execution($session);
            $initial = clone $session->instance->registry->replication;
            try {
                $execution->statement($missing);
                self::fail('A retained condition precedes missing channel lookup.');
            } catch (SqlError $error) {
                self::assertSame($expected, $error->getCode());
            }
            self::assertEquals($initial, $session->instance->registry->replication);
            self::assertSame([], array_keys($session->instance->registry->channels));
            self::assertInstanceOf(Completion::class, $execution->statement($configure));
            $channel = $configure->channel->value ?? '';
            $state = $channel === '' ? $session->instance->registry->replication : $session->instance->registry->channels[$channel];
            $before = clone $state;
            self::assertSame('source.example', $state->host);
            try {
                $execution->statement($configured);
                self::fail('A retained condition precedes changing a configured channel.');
            } catch (SqlError $error) {
                self::assertSame($expected, $error->getCode());
            }
            self::assertEquals($before, $state);
            self::assertSame('generated replication requests without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
    /**
     * Defining a procedure evaluates only reading conditions; missing credentials wait for CALL.
     */
    #[Test]
    #[DataProvider('providerGroupReleases')]
    public function satisfiesStoredCredentialsKeepTheirConditionPhase(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Replication\Group\StartGroupReplicationGenerator::stored($release))->then(static function (array $input) use ($release): void {
            [$definition, $call, $code] = $input;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $definition);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall::class, $call);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'generated group credentials without SQL';
            $execution = new Execution($session);
            if ($code === 3971) {
                self::assertInstanceOf(Completion::class, $execution->statement($definition));
                try {
                    $execution->statement($call);
                    self::fail('A stored password without its user fails when invoked.');
                } catch (SqlError $error) {
                    self::assertSame($code, $error->getCode());
                }
            } else {
                try {
                    $execution->statement($definition);
                    self::fail('An invalid credential prevents installing the procedure.');
                } catch (SqlError $error) {
                    self::assertSame($code, $error->getCode());
                }
                self::assertSame([], $session->instance->dictionary->schema('pbt')?->procedures);
            }
            self::assertSame('generated group credentials without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerGroupReleases(): iterable
    {
        foreach (self::providerReleases() as $name => [$release]) {
            if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                yield $name => [$release];
            }
        }
    }

}
