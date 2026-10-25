<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Replication\Reset;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Replication\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset as Model;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class ResetProperties extends TestCase
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new \Tests\Generator\Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }
    /**
     * Runs the generated analysis result through the ordinary dispatch and execution boundary.
     * @param non-negative-int $sample
     * @throws JsonException When the semantic type inventory cannot be read
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        $statement = (new Embedding())->statement($value);
        $operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, new Choices('context:' . $sample));
        $profile = $operation->profile();
        $database = $operation->context->searchPath[0]->value;
        $mode = (new \Tests\Generator\Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, \MySqlMemory\Typing\Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (\MySqlMemory\Error\SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerResetTargetsSeeds(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            yield 'reset:' . $seed => [(string) $seed];
        }
    }

    /**
     * @throws JsonException When the value inventory is invalid
     */
    #[DataProvider('providerResetTargetsSeeds')]
    #[Test]
    public function satisfiesGeneratedLogIndexesExecuteWithRepeatedTargets(string $seed): void
    {
        $logs = (new Construction(new Choices('reset-index:' . $seed), new Catalogue(), new Metadata()))->object(ResetBinaryLogs::class, 4);
        self::assertInstanceOf(ResetBinaryLogs::class, $logs);
        $session = (new Instance($logs->synonym ? '8.3.0' : ($logs->terminology === Terminology::Legacy ? '8.0.44' : '8.4.7')))->connect();
        $session->text = 'unrelated invalid input';
        $statement = new Model([new ResetReplica(Terminology::Current, true, new Text('missing')), $logs, new ResetBinaryLogs($logs->terminology), new ResetReplica(Terminology::Current)]);
        $statement1 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        self::assertInstanceOf(Completion::class, (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []));
        self::assertSame([$logs->first === null ? 1 : (new Magnitudes())->integer($logs->first)], $session->instance->registry->binaryLog->files);
        self::assertSame(1, $session->instance->registry->status->read('Flush_commands'));
        $deprecated = Deprecated::ResetMaster;
        self::assertSame($logs->synonym ? array_fill(0, 2, ['Warning', $deprecated->code(), $deprecated->value]) : [], $session->diagnostics->conditions);
    }

    /**
     * @throws JsonException When the value inventory is invalid
     */
    #[DataProvider('providerResetTargetsSeeds')]
    #[Test]
    public function satisfiesGeneratedReplicaOptionsCanBeOverriddenWithoutInputSql(string $seed): void
    {
        $replica = (new Construction(new Choices('reset-replica:' . $seed), new Catalogue(), new Metadata()))->object(ResetReplica::class, 4);
        self::assertInstanceOf(ResetReplica::class, $replica);
        $session = (new Instance($replica->terminology === Terminology::Legacy ? '5.7.44' : '8.0.44', ['server_id' => '1']))->connect();
        $session->text = 'unrelated invalid input';
        $statement = new Model([$replica, new ResetReplica($replica->terminology)]);
        $statement2 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        self::assertInstanceOf(Completion::class, (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []));
        self::assertFalse($session->instance->registry->replication->initialized);
        self::assertSame([], array_values(array_filter($session->diagnostics->conditions, static fn (array $condition): bool => $condition[0] === 'Error')));
    }
}
