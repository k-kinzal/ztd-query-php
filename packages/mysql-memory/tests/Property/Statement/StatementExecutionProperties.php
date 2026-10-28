<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generator;
use Eris\TestTrait;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Statement;
use Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator;

/**
 * Exercises typed statement operands through planning and execution without SQL input.
 * SQL errors are observable replies; invalid model values and implementation errors fail.
 */
#[CoversNothing]
#[Large]
final class StatementExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Index-listing alternatives read the index of their generated declaration.
     */
    #[Test]
    #[DataProvider('providerMaintenanceReleases')]
    public function satisfiesIndexInspectionReadsItsGeneratedTable(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowKeysGenerator::withTable($release))->then(static function (array $sample) use ($release): void {
            [$definition, $request] = $sample;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $definition);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys::class, $request);
            $index = $definition->elements[1];
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition::class, $index);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart::class, $index->parts[0]);
            $database = $definition->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), ['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'character_set_results' => 'utf8mb4', 'collation_connection' => 'utf8mb4_unicode_ci'], [$database]))->connect(database: $database);
            $session->text = 'Generated index inspection without SQL';
            $execution = new Execution($session);
            self::assertInstanceOf(Completion::class, $execution->statement($definition));
            $reply = $execution->statement($request);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertCount(1, $reply->rows);
            self::assertSame($definition->name->name->value, $reply->rows[0][0]);
            self::assertSame($index->name?->column->value, $reply->rows[0][2]);
            self::assertSame(\MySqlMemory\Result\ColumnFlag::NotNull->value, $reply->columns[2]->flags & \MySqlMemory\Result\ColumnFlag::NotNull->value);
            self::assertSame($index->parts[0]->column->value, $reply->rows[0][4]);
            self::assertSame('Generated index inspection without SQL', $session->text);
        });
    }

    /**
     * Keyword choices retain the dependent table and the native maintenance result.
     */
    #[Test]
    #[DataProvider('providerMaintenanceReleases')]
    public function satisfiesMaintenanceExecutesAgainstItsGeneratedDeclaration(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Server\Maintenance\AnalyzeTableGenerator::withTable($release))->then(static function (array $sample) use ($release): void {
            [$definition, $request] = $sample;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $definition);
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $request);
            $database = $definition->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), databases: [$database]))->connect(database: $database);
            $session->text = 'Generated maintenance without SQL input';
            $execution = new Execution($session);
            self::assertInstanceOf(Completion::class, $execution->statement($definition));
            $reply = $execution->statement($request);
            self::assertInstanceOf(ResultSet::class, $reply);
            $label = $database . '.' . $definition->name->name->value;
            $expected = match (true) {
                $request instanceof \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable => [[$label, 'analyze', 'status', 'OK']],
                $request instanceof \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable => [[$label, 'optimize', 'note', 'Table does not support optimize, doing recreate + analyze instead'], [$label, 'optimize', 'status', 'OK']],
                $request instanceof \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable => [[$label, 'repair', 'note', "The storage engine for the table doesn't support repair"]],
                default => self::fail('A generated maintenance request retains its concrete operation.'),
            };
            self::assertSame($expected, $reply->rows);
            self::assertNotNull($session->instance->dictionary->schema($database)?->table($definition->name->name->value));
            self::assertSame('Generated maintenance without SQL input', $session->text);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerMaintenanceReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }

    /**
     * Every generated structure reaches the executor with its local semantic information.
     *
     * @param Generator<Statement> $statements The composed SQL forms
     * @param Generator<\SqlSemantics\Contract\AnalysisContext>|null $contexts The generated forms' required profile and declarations
     */
    #[Test]
    #[DataProvider('providerStatements')]
    public function satisfiesGeneratedStatementsProduceSqlReplies(Generator $statements, GrammarRelease $release = GrammarRelease::MySql847, ?Generator $contexts = null): void
    {
        $this->forAll(new AnalyzedStatementGenerator($release, $statements, $contexts))->withMaxSize(10)->then(static function (Statement $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Semantic input supplied directly, without source SQL';
            $execution = new Execution($session);
            try {
                $reply = $execution->statement($statement);
                self::assertTrue($reply instanceof ResultSet || $reply instanceof Completion);
                if ($reply instanceof ResultSet) {
                    foreach ($reply->rows as $row) {
                        self::assertCount(count($reply->columns), $row);
                    }
                } else {
                    self::assertGreaterThanOrEqual(0, $reply->affectedRows);
                }
                self::assertGreaterThanOrEqual(0, $reply->warnings());
            } catch (SqlError $error) {
                $execution->failed($error);
                self::assertGreaterThan(0, $error->getCode());
                self::assertMatchesRegularExpression('/^[A-Z0-9]{5}$/D', $error->sqlState());
                self::assertNotSame('', $error->getMessage());
                self::assertSame(-1, $session->variables->rowCount);
                self::assertGreaterThan(0, $session->diagnostics->errors());
            }
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * @return iterable<string, array{0: Generator<Statement>, 1?: GrammarRelease, 2?: Generator<\SqlSemantics\Contract\AnalysisContext>|null}>
     */
    public static function providerStatements(): iterable
    {
        yield 'Empty input' => [new \Tests\Generator\MySql\Statement\EmptyStatementGenerator()];
        yield 'Legacy escaped relation' => [new \Tests\Generator\MySql\Statement\QueryRootGenerator(new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
            \Eris\Generators::constant([]),
            \Eris\Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\StarGenerator()),
            new \Tests\Generator\MySql\Statement\Relation\EscapedRelationGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\MySql\Statement\Relation\TableReferenceGenerator(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()))),
        )), GrammarRelease::MySql5744];
        yield 'Nested JSON columns' => [new \Tests\Generator\MySql\Statement\QueryRootGenerator(new \Tests\Generator\MySql\Statement\Query\SelectGenerator(
            \Eris\Generators::constant([]),
            \Eris\Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\StarGenerator()),
            from: \Tests\Generator\MySql\Statement\Relation\JsonTableGenerator::nestedOrdinals(),
        ))];
        foreach (\Tests\Generator\Semantics\Statement\ScalarGenerator::expressionForms() as $name => $expressions) {
            yield $name => [new \Tests\Generator\MySql\Statement\QueryRootGenerator(
                new \Tests\Generator\MySql\Statement\Query\SelectGenerator(\Eris\Generators::constant([]), \Eris\Generators::vector(1, new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator($expressions))),
            ), GrammarRelease::MySql847, $name === 'Concatenation operands' ? \Tests\Generator\Semantics\Contract\AnalysisContextGenerator::mysql(GrammarRelease::MySql847, new \Tests\Generator\MySql\ModeGenerator(pipesAsConcat: \Eris\Generators::constant(true))) : null];
        }

        $forms = (static function (): iterable {
            yield from \Tests\Generator\MySql\Statement\Account\AlterDefaultRoleGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\AlterRegistrationGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\AlterUserGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\CreateRoleGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\CreateUserGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\DropRoleGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\DropUserGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\Privilege\GrantProxyGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\Privilege\GrantRolesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\Privilege\RevokeAllGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\Privilege\RevokePrivilegesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\Privilege\RevokeProxyGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\Privilege\RevokeRolesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\RenameUserGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\ResourceGroup\AlterResourceGroupGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\ResourceGroup\CreateResourceGroupGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\ResourceGroup\DropResourceGroupGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\ResourceGroup\SetResourceGroupGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\SetDefaultRoleGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\SetPasswordGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Account\SetRoleGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Alter\AlterTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Alter\DropIndexGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Alter\DropTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Alter\RenameTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Alter\TruncateTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\DeleteGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Handler\HandlerCloseGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Handler\HandlerIndexReadGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Handler\HandlerIndexSeekGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Handler\HandlerOpenGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Handler\HandlerScanGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\ImportTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Insert\InsertQueryGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Insert\InsertRowsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Insert\InsertSetGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Load\LoadTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Prepared\DeallocateGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Prepared\ExecuteGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\Prepared\PrepareGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\ProcedureCallGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Dml\UpdateGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\QueryRootGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Filter\ChangeReplicationFilterGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Group\StartGroupReplicationGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Group\StopGroupReplicationGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Log\BinlogEventGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Log\PurgeLogsBeforeGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Log\PurgeLogsToGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Replica\StartReplicaGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Replica\StopReplicaGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Reset\ResetGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Reset\ResetPersistGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Replication\Source\ChangeReplicationSourceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\AlterEventGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\AlterRoutineGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnosticsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\Condition\ResignalGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\Condition\SignalGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\CreateEventGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\CreateFunctionGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\CreateLoadableFunctionGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\CreateProcedureGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\CreateTriggerGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Routine\DropProgramGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Database\AlterDatabaseGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Database\CreateDatabaseGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Database\DropDatabaseGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Flush\FlushGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Flush\FlushTablesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\ForeignServer\AlterServerGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\ForeignServer\CreateServerGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\ForeignServer\DropServerGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Instance\AlterInstanceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Instance\CloneInstanceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Instance\CloneLocalGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Instance\KillGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Instance\ShutdownGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\KeyCache\CacheIndexGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\KeyCache\LoadIndexGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Lock\LockInstanceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Lock\UnlockInstanceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Lock\UnlockTablesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Maintenance\AnalyzeTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Maintenance\CheckTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Maintenance\ChecksumTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Maintenance\OptimizeTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Maintenance\RepairTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Plugin\InstallComponentGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Plugin\InstallPluginGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Plugin\UninstallComponentGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Plugin\UninstallPluginGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Spatial\CreateSpatialReferenceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Spatial\DropSpatialReferenceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\AlterLogfileGroupGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\AlterTablespaceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\AlterTablespaceDatafileGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\AlterUndoTablespaceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\CreateLogfileGroupGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\CreateTablespaceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\CreateUndoTablespaceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\DropLogfileGroupGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\DropTablespaceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\DropUndoTablespaceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\RenameTablespaceGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\BeginGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\CommitGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\ReleaseSavepointGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\RollbackGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\RollbackToSavepointGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\SavepointGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\StartTransactionGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaCommitGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaEndGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaPrepareGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaRecoverGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaRollbackGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaStartGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Table\CreateIndexGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Table\CreateTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Table\CreateTableLikeGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Explain\DescribeTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Explain\ExplainGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Explain\ExplainConnectionGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Explain\HelpGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Explain\UseDatabaseGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Set\SetTransactionGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Set\SetVariablesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateEventGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateFunctionGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateProcedureGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateTriggerGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Program\ShowFunctionCodeGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Program\ShowFunctionStatusGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Program\ShowProcedureCodeGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Program\ShowProcedureStatusGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowBinaryLogStatusGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowBinaryLogsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowBinlogEventsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowRelaylogEventsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowReplicaStatusGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowReplicasGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowColumnsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateDatabaseGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateTableGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateViewGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowDatabasesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowEventsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowKeysGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowOpenTablesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTableStatusGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTablesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTriggersGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCharacterSetGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCollationGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCreateUserGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineCatalogGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineLogsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineMutexGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineStatusGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowGrantsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowPluginsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowPrivilegesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowProcesslistGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowStatusGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Server\ShowVariablesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Session\ShowErrorCountGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Session\ShowErrorsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Session\ShowProfileGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Session\ShowProfilesGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Session\ShowWarningCountGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\Session\ShowWarningsGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\Utility\Show\ShowParseTreeGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\View\AlterViewGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\View\CreateViewGenerator::forms();
            yield from \Tests\Generator\MySql\Statement\View\DropViewGenerator::forms();
        })();
        foreach ($forms as $name => $generator) {
            yield $name => [$generator];
        }
        $additional = (static function (): iterable {
            yield from \Tests\Generator\MySql\Statement\Account\ExpireUserPasswordsGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Account\SetPasswordGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Alter\AlterTableGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Dml\Insert\InsertQueryGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Dml\Insert\InsertRowsGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Dml\MultipleDeleteGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\QueryRootGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Replication\Reset\ResetGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Routine\Condition\SignalGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Routine\CreateProcedureGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Routine\CreateTriggerGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Server\Database\AlterDatabaseGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Server\Database\UpgradeDatabaseNameGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\AlterTablespaceAccessGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Server\Storage\CreateTablespaceGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Table\CreateIndexGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Table\CreateTableGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Utility\Explain\ExplainGenerator::additionalForms();
            yield from \Tests\Generator\MySql\Statement\Utility\Set\SetVariablesGenerator::additionalForms();
        })();
        foreach ($additional as $name => [$release, $statement]) {
            yield $name => [$statement, $release];
        }
    }
    /**
     * Explicit absence is permitted only by the semantics of these commands.
     *
     * @param Generator<Statement> $statements Locally bound absent-table requests
     */
    #[Test]
    #[DataProvider('providerAbsentTables')]
    public function satisfiesPermittedAbsentTablesProduceCompletion(Generator $statements, int $warnings): void
    {
        $this->forAll($statements)->then(static function (Statement $statement) use ($warnings): void {
            $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(Completion::class, $reply);
            self::assertSame(0, $reply->affectedRows);
            self::assertSame($warnings, $reply->warnings());
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * @return iterable<string, array{Generator<Statement>, int}>
     */
    public static function providerAbsentTables(): iterable
    {
        yield 'DROP IF EXISTS' => [\Tests\Generator\MySql\Statement\Alter\AbsentTableGenerator::statements(), 1];
        yield 'GRANT CREATE' => [\Tests\Generator\MySql\Statement\Account\Privilege\Level\AbsentGrantTableGenerator::statements(), 0];
    }

}
