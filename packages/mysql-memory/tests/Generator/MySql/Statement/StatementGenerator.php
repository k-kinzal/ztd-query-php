<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Statement;
use Tests\Generator\MySql\Statement\Account\AlterDefaultRoleGenerator;
use Tests\Generator\MySql\Statement\Account\AlterRegistrationGenerator;
use Tests\Generator\MySql\Statement\Account\AlterUserGenerator;
use Tests\Generator\MySql\Statement\Account\CreateRoleGenerator;
use Tests\Generator\MySql\Statement\Account\CreateUserGenerator;
use Tests\Generator\MySql\Statement\Account\DropRoleGenerator;
use Tests\Generator\MySql\Statement\Account\DropUserGenerator;
use Tests\Generator\MySql\Statement\Account\ExpireUserPasswordsGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantProxyGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantRolesGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\RevokeAllGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\RevokePrivilegesGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\RevokeProxyGenerator;
use Tests\Generator\MySql\Statement\Account\Privilege\RevokeRolesGenerator;
use Tests\Generator\MySql\Statement\Account\RenameUserGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\AlterResourceGroupGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\CreateResourceGroupGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\DropResourceGroupGenerator;
use Tests\Generator\MySql\Statement\Account\ResourceGroup\SetResourceGroupGenerator;
use Tests\Generator\MySql\Statement\Account\SetDefaultRoleGenerator;
use Tests\Generator\MySql\Statement\Account\SetPasswordGenerator;
use Tests\Generator\MySql\Statement\Account\SetRoleGenerator;
use Tests\Generator\MySql\Statement\Alter\AlterTableGenerator;
use Tests\Generator\MySql\Statement\Alter\DropIndexGenerator;
use Tests\Generator\MySql\Statement\Alter\DropTableGenerator;
use Tests\Generator\MySql\Statement\Alter\RenameTableGenerator;
use Tests\Generator\MySql\Statement\Alter\TruncateTableGenerator;
use Tests\Generator\MySql\Statement\Dml\DeleteGenerator;
use Tests\Generator\MySql\Statement\Dml\EvaluationGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerCloseGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerIndexReadGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerIndexSeekGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerOpenGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\HandlerScanGenerator;
use Tests\Generator\MySql\Statement\Dml\ImportTableGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertQueryGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertRowsGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertSetGenerator;
use Tests\Generator\MySql\Statement\Dml\Load\LoadTableGenerator;
use Tests\Generator\MySql\Statement\Dml\MultipleDeleteGenerator;
use Tests\Generator\MySql\Statement\Dml\Prepared\DeallocateGenerator;
use Tests\Generator\MySql\Statement\Dml\Prepared\ExecuteGenerator;
use Tests\Generator\MySql\Statement\Dml\Prepared\PrepareGenerator;
use Tests\Generator\MySql\Statement\Dml\ProcedureCallGenerator;
use Tests\Generator\MySql\Statement\Dml\UpdateGenerator;
use Tests\Generator\MySql\Statement\Replication\Filter\ChangeReplicationFilterGenerator;
use Tests\Generator\MySql\Statement\Replication\Group\StartGroupReplicationGenerator;
use Tests\Generator\MySql\Statement\Replication\Group\StopGroupReplicationGenerator;
use Tests\Generator\MySql\Statement\Replication\Log\BinlogEventGenerator;
use Tests\Generator\MySql\Statement\Replication\Log\PurgeLogsBeforeGenerator;
use Tests\Generator\MySql\Statement\Replication\Log\PurgeLogsToGenerator;
use Tests\Generator\MySql\Statement\Replication\Replica\StartReplicaGenerator;
use Tests\Generator\MySql\Statement\Replication\Replica\StopReplicaGenerator;
use Tests\Generator\MySql\Statement\Replication\Reset\ResetGenerator;
use Tests\Generator\MySql\Statement\Replication\Reset\ResetPersistGenerator;
use Tests\Generator\MySql\Statement\Replication\Source\ChangeReplicationSourceGenerator;
use Tests\Generator\MySql\Statement\Routine\AlterEventGenerator;
use Tests\Generator\MySql\Statement\Routine\AlterRoutineGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnosticsGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\ResignalGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\SignalGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateEventGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateFunctionGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateLoadableFunctionGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateProcedureGenerator;
use Tests\Generator\MySql\Statement\Routine\CreateTriggerGenerator;
use Tests\Generator\MySql\Statement\Routine\DropProgramGenerator;
use Tests\Generator\MySql\Statement\Server\Database\AlterDatabaseGenerator;
use Tests\Generator\MySql\Statement\Server\Database\CreateDatabaseGenerator;
use Tests\Generator\MySql\Statement\Server\Database\DropDatabaseGenerator;
use Tests\Generator\MySql\Statement\Server\Database\UpgradeDatabaseNameGenerator;
use Tests\Generator\MySql\Statement\Server\Flush\FlushGenerator;
use Tests\Generator\MySql\Statement\Server\Flush\FlushTablesGenerator;
use Tests\Generator\MySql\Statement\Server\ForeignServer\AlterServerGenerator;
use Tests\Generator\MySql\Statement\Server\ForeignServer\CreateServerGenerator;
use Tests\Generator\MySql\Statement\Server\ForeignServer\DropServerGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\AlterInstanceGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\CloneInstanceGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\CloneLocalGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\KillGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\RestartGenerator;
use Tests\Generator\MySql\Statement\Server\Instance\ShutdownGenerator;
use Tests\Generator\MySql\Statement\Server\KeyCache\CacheIndexGenerator;
use Tests\Generator\MySql\Statement\Server\KeyCache\LoadIndexGenerator;
use Tests\Generator\MySql\Statement\Server\Lock\LockInstanceGenerator;
use Tests\Generator\MySql\Statement\Server\Lock\LockTablesGenerator;
use Tests\Generator\MySql\Statement\Server\Lock\UnlockInstanceGenerator;
use Tests\Generator\MySql\Statement\Server\Lock\UnlockTablesGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\AnalyzeTableGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\ChecksumTableGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\CheckTableGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\OptimizeTableGenerator;
use Tests\Generator\MySql\Statement\Server\Maintenance\RepairTableGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\InstallComponentGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\InstallPluginGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\UninstallComponentGenerator;
use Tests\Generator\MySql\Statement\Server\Plugin\UninstallPluginGenerator;
use Tests\Generator\MySql\Statement\Server\Spatial\CreateSpatialReferenceGenerator;
use Tests\Generator\MySql\Statement\Server\Spatial\DropSpatialReferenceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\AlterLogfileGroupGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\AlterTablespaceAccessGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\AlterTablespaceDatafileGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\AlterTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\AlterUndoTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\CreateLogfileGroupGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\CreateTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\CreateUndoTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\DropLogfileGroupGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\DropTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\DropUndoTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Storage\RenameTablespaceGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\BeginGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\CommitGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\ReleaseSavepointGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\RollbackGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\RollbackToSavepointGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\SavepointGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\StartTransactionGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaCommitGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaEndGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaPrepareGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaRecoverGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaRollbackGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaStartGenerator;
use Tests\Generator\MySql\Statement\Table\CreateIndexGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableLikeGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\DescribeTableGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\ExplainConnectionGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\ExplainGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\HelpGenerator;
use Tests\Generator\MySql\Statement\Utility\Explain\UseDatabaseGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\SetTransactionGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\SetVariablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateEventGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateFunctionGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateProcedureGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowCreateTriggerGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowFunctionCodeGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowFunctionStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowProcedureCodeGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Program\ShowProcedureStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowBinaryLogsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowBinaryLogStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowBinlogEventsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowRelaylogEventsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowReplicasGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Replication\ShowReplicaStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowColumnsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateDatabaseGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateTableGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateViewGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowDatabasesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowEventsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowKeysGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowOpenTablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTableStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowTriggersGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCharacterSetGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCollationGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowCreateUserGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineCatalogGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineLogsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineMutexGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowEngineStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowGrantsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowPluginsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowPrivilegesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowProcesslistGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowStatusGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Server\ShowVariablesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowErrorCountGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowErrorsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowProfileGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowProfilesGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowWarningCountGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\Session\ShowWarningsGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\ShowParseTreeGenerator;
use Tests\Generator\MySql\Statement\View\AlterViewGenerator;
use Tests\Generator\MySql\Statement\View\CreateViewGenerator;
use Tests\Generator\MySql\Statement\View\DropViewGenerator;

/**
 * Generates Statement values from the generators of its operands.
 *
 * @implements ErisGenerator<Statement>
 */
final class StatementGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Statement> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Statement from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 1, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 510, 510, 510, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 508, 511, 511, 511, 511, 511, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 510, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new AlterDefaultRoleGenerator($release),
                1 => new AlterRegistrationGenerator($release),
                2 => new AlterUserGenerator($release),
                3 => new CreateRoleGenerator($release),
                4 => new CreateUserGenerator($release),
                5 => new DropRoleGenerator($release),
                6 => new DropUserGenerator($release),
                7 => new ExpireUserPasswordsGenerator($release),
                8 => new GrantPrivilegesGenerator($release),
                9 => new GrantProxyGenerator($release),
                10 => new GrantRolesGenerator($release),
                11 => new RevokeAllGenerator($release),
                12 => new RevokePrivilegesGenerator($release),
                13 => new RevokeProxyGenerator($release),
                14 => new RevokeRolesGenerator($release),
                15 => new RenameUserGenerator($release),
                16 => new AlterResourceGroupGenerator($release),
                17 => new CreateResourceGroupGenerator($release),
                18 => new DropResourceGroupGenerator($release),
                19 => new SetResourceGroupGenerator($release),
                20 => new SetDefaultRoleGenerator($release),
                21 => new SetPasswordGenerator($release),
                22 => new SetRoleGenerator($release),
                23 => new AlterTableGenerator($release, $depth - 1),
                24 => new DropIndexGenerator($release),
                25 => new DropTableGenerator($release),
                26 => new RenameTableGenerator($release),
                27 => new TruncateTableGenerator($release),
                28 => new DeleteGenerator($release, $depth - 1),
                29 => new EvaluationGenerator($release, $depth - 1),
                30 => new HandlerCloseGenerator($release),
                31 => new HandlerIndexReadGenerator($release, $depth - 1),
                32 => new HandlerIndexSeekGenerator($release, $depth - 1),
                33 => new HandlerOpenGenerator($release),
                34 => new HandlerScanGenerator($release, $depth - 1),
                35 => new ImportTableGenerator($release),
                36 => new InsertQueryGenerator($release, $depth - 1),
                37 => new InsertRowsGenerator($release, $depth - 1),
                38 => new InsertSetGenerator($release, $depth - 1),
                39 => new LoadTableGenerator($release, $depth - 1),
                40 => new MultipleDeleteGenerator($release, $depth - 1),
                41 => new DeallocateGenerator($release),
                42 => new ExecuteGenerator($release),
                43 => new PrepareGenerator($release),
                44 => new ProcedureCallGenerator($release, $depth - 1),
                45 => new UpdateGenerator($release, $depth - 1),
                46 => new EmptyStatementGenerator($release),
                47 => new QueryRootGenerator($release, $depth - 1),
                48 => new ChangeReplicationFilterGenerator($release),
                49 => new StartGroupReplicationGenerator($release),
                50 => new StopGroupReplicationGenerator($release),
                51 => new BinlogEventGenerator($release),
                52 => new PurgeLogsBeforeGenerator($release, $depth - 1),
                53 => new PurgeLogsToGenerator($release),
                54 => new StartReplicaGenerator($release),
                55 => new StopReplicaGenerator($release),
                56 => new ResetGenerator($release),
                57 => new ResetPersistGenerator($release),
                58 => new ChangeReplicationSourceGenerator($release),
                59 => new AlterEventGenerator($release, $depth - 1),
                60 => new AlterRoutineGenerator($release),
                61 => new GetDiagnosticsGenerator($release, $depth - 1),
                62 => new ResignalGenerator($release, $depth - 1),
                63 => new SignalGenerator($release, $depth - 1),
                64 => new CreateEventGenerator($release, $depth - 1),
                65 => new CreateFunctionGenerator($release, $depth - 1),
                66 => new CreateLoadableFunctionGenerator($release),
                67 => new CreateProcedureGenerator($release, $depth - 1),
                68 => new CreateTriggerGenerator($release, $depth - 1),
                69 => new DropProgramGenerator($release),
                70 => new AlterDatabaseGenerator($release),
                71 => new CreateDatabaseGenerator($release),
                72 => new DropDatabaseGenerator($release),
                73 => new UpgradeDatabaseNameGenerator($release),
                74 => new FlushGenerator($release),
                75 => new FlushTablesGenerator($release),
                76 => new AlterServerGenerator($release),
                77 => new CreateServerGenerator($release),
                78 => new DropServerGenerator($release),
                79 => new AlterInstanceGenerator($release),
                80 => new CloneInstanceGenerator($release),
                81 => new CloneLocalGenerator($release),
                82 => new KillGenerator($release, $depth - 1),
                83 => new RestartGenerator($release),
                84 => new ShutdownGenerator($release),
                85 => new CacheIndexGenerator($release),
                86 => new LoadIndexGenerator($release),
                87 => new LockInstanceGenerator($release),
                88 => new LockTablesGenerator($release),
                89 => new UnlockInstanceGenerator($release),
                90 => new UnlockTablesGenerator($release),
                91 => new AnalyzeTableGenerator($release),
                92 => new CheckTableGenerator($release),
                93 => new ChecksumTableGenerator($release),
                94 => new OptimizeTableGenerator($release),
                95 => new RepairTableGenerator($release),
                96 => new InstallComponentGenerator($release, $depth - 1),
                97 => new InstallPluginGenerator($release),
                98 => new UninstallComponentGenerator($release),
                99 => new UninstallPluginGenerator($release),
                100 => new CreateSpatialReferenceGenerator($release),
                101 => new DropSpatialReferenceGenerator($release),
                102 => new AlterLogfileGroupGenerator($release),
                103 => new AlterTablespaceGenerator($release),
                104 => new AlterTablespaceAccessGenerator($release),
                105 => new AlterTablespaceDatafileGenerator($release),
                106 => new AlterUndoTablespaceGenerator($release),
                107 => new CreateLogfileGroupGenerator($release),
                108 => new CreateTablespaceGenerator($release),
                109 => new CreateUndoTablespaceGenerator($release),
                110 => new DropLogfileGroupGenerator($release),
                111 => new DropTablespaceGenerator($release),
                112 => new DropUndoTablespaceGenerator($release),
                113 => new RenameTablespaceGenerator($release),
                114 => new BeginGenerator($release),
                115 => new CommitGenerator($release),
                116 => new ReleaseSavepointGenerator($release),
                117 => new RollbackGenerator($release),
                118 => new RollbackToSavepointGenerator($release),
                119 => new SavepointGenerator($release),
                120 => new StartTransactionGenerator($release),
                121 => new XaCommitGenerator($release),
                122 => new XaEndGenerator($release),
                123 => new XaPrepareGenerator($release),
                124 => new XaRecoverGenerator($release),
                125 => new XaRollbackGenerator($release),
                126 => new XaStartGenerator($release),
                127 => new CreateIndexGenerator($release, $depth - 1),
                128 => new CreateTableGenerator($release, $depth - 1),
                129 => new CreateTableLikeGenerator($release),
                130 => new DescribeTableGenerator($release),
                131 => new ExplainGenerator($release, $depth - 1),
                132 => new ExplainConnectionGenerator($release),
                133 => new HelpGenerator($release),
                134 => new UseDatabaseGenerator($release),
                135 => new SetTransactionGenerator($release),
                136 => new SetVariablesGenerator($release, $depth - 1),
                137 => new ShowCreateEventGenerator($release),
                138 => new ShowCreateFunctionGenerator($release),
                139 => new ShowCreateProcedureGenerator($release),
                140 => new ShowCreateTriggerGenerator($release),
                141 => new ShowFunctionCodeGenerator($release),
                142 => new ShowFunctionStatusGenerator($release, $depth - 1),
                143 => new ShowProcedureCodeGenerator($release),
                144 => new ShowProcedureStatusGenerator($release, $depth - 1),
                145 => new ShowBinaryLogStatusGenerator($release),
                146 => new ShowBinaryLogsGenerator($release),
                147 => new ShowBinlogEventsGenerator($release),
                148 => new ShowRelaylogEventsGenerator($release),
                149 => new ShowReplicaStatusGenerator($release),
                150 => new ShowReplicasGenerator($release),
                151 => new ShowColumnsGenerator($release, $depth - 1),
                152 => new ShowCreateDatabaseGenerator($release),
                153 => new ShowCreateTableGenerator($release),
                154 => new ShowCreateViewGenerator($release),
                155 => new ShowDatabasesGenerator($release, $depth - 1),
                156 => new ShowEventsGenerator($release, $depth - 1),
                157 => new ShowKeysGenerator($release, $depth - 1),
                158 => new ShowOpenTablesGenerator($release, $depth - 1),
                159 => new ShowTableStatusGenerator($release, $depth - 1),
                160 => new ShowTablesGenerator($release, $depth - 1),
                161 => new ShowTriggersGenerator($release, $depth - 1),
                162 => new ShowCharacterSetGenerator($release, $depth - 1),
                163 => new ShowCollationGenerator($release, $depth - 1),
                164 => new ShowCreateUserGenerator($release),
                165 => new ShowEngineCatalogGenerator($release),
                166 => new ShowEngineLogsGenerator($release),
                167 => new ShowEngineMutexGenerator($release),
                168 => new ShowEngineStatusGenerator($release),
                169 => new ShowGrantsGenerator($release),
                170 => new ShowPluginsGenerator($release),
                171 => new ShowPrivilegesGenerator($release),
                172 => new ShowProcesslistGenerator($release),
                173 => new ShowStatusGenerator($release, $depth - 1),
                174 => new ShowVariablesGenerator($release, $depth - 1),
                175 => new ShowErrorCountGenerator($release),
                176 => new ShowErrorsGenerator($release),
                177 => new ShowProfileGenerator($release),
                178 => new ShowProfilesGenerator($release),
                179 => new ShowWarningCountGenerator($release),
                180 => new ShowWarningsGenerator($release),
                181 => new ShowParseTreeGenerator($release, $depth - 1),
                182 => new AlterViewGenerator($release, $depth - 1),
                183 => new CreateViewGenerator($release, $depth - 1),
                184 => new DropViewGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 1, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 510, 510, 510, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 3, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 3, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 510, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new AlterDefaultRoleGenerator($release),
                1 => new AlterRegistrationGenerator($release),
                2 => new AlterUserGenerator($release),
                3 => new CreateRoleGenerator($release),
                4 => new CreateUserGenerator($release),
                5 => new DropRoleGenerator($release),
                6 => new DropUserGenerator($release),
                7 => new ExpireUserPasswordsGenerator($release),
                8 => new GrantPrivilegesGenerator($release),
                9 => new GrantProxyGenerator($release),
                10 => new GrantRolesGenerator($release),
                11 => new RevokeAllGenerator($release),
                12 => new RevokePrivilegesGenerator($release),
                13 => new RevokeProxyGenerator($release),
                14 => new RevokeRolesGenerator($release),
                15 => new RenameUserGenerator($release),
                16 => new AlterResourceGroupGenerator($release),
                17 => new CreateResourceGroupGenerator($release),
                18 => new DropResourceGroupGenerator($release),
                19 => new SetResourceGroupGenerator($release),
                20 => new SetDefaultRoleGenerator($release),
                21 => new SetPasswordGenerator($release),
                22 => new SetRoleGenerator($release),
                23 => new DropIndexGenerator($release),
                24 => new DropTableGenerator($release),
                25 => new RenameTableGenerator($release),
                26 => new TruncateTableGenerator($release),
                27 => new HandlerCloseGenerator($release),
                28 => new HandlerOpenGenerator($release),
                29 => new ImportTableGenerator($release),
                30 => new DeallocateGenerator($release),
                31 => new ExecuteGenerator($release),
                32 => new PrepareGenerator($release),
                33 => new EmptyStatementGenerator($release),
                34 => new ChangeReplicationFilterGenerator($release),
                35 => new StartGroupReplicationGenerator($release),
                36 => new StopGroupReplicationGenerator($release),
                37 => new BinlogEventGenerator($release),
                38 => new PurgeLogsToGenerator($release),
                39 => new StartReplicaGenerator($release),
                40 => new StopReplicaGenerator($release),
                41 => new ResetGenerator($release),
                42 => new ResetPersistGenerator($release),
                43 => new ChangeReplicationSourceGenerator($release),
                44 => new AlterRoutineGenerator($release),
                45 => new CreateLoadableFunctionGenerator($release),
                46 => new DropProgramGenerator($release),
                47 => new AlterDatabaseGenerator($release),
                48 => new CreateDatabaseGenerator($release),
                49 => new DropDatabaseGenerator($release),
                50 => new UpgradeDatabaseNameGenerator($release),
                51 => new FlushGenerator($release),
                52 => new FlushTablesGenerator($release),
                53 => new AlterServerGenerator($release),
                54 => new CreateServerGenerator($release),
                55 => new DropServerGenerator($release),
                56 => new AlterInstanceGenerator($release),
                57 => new CloneInstanceGenerator($release),
                58 => new CloneLocalGenerator($release),
                59 => new RestartGenerator($release),
                60 => new ShutdownGenerator($release),
                61 => new CacheIndexGenerator($release),
                62 => new LoadIndexGenerator($release),
                63 => new LockInstanceGenerator($release),
                64 => new LockTablesGenerator($release),
                65 => new UnlockInstanceGenerator($release),
                66 => new UnlockTablesGenerator($release),
                67 => new AnalyzeTableGenerator($release),
                68 => new CheckTableGenerator($release),
                69 => new ChecksumTableGenerator($release),
                70 => new OptimizeTableGenerator($release),
                71 => new RepairTableGenerator($release),
                72 => new InstallPluginGenerator($release),
                73 => new UninstallComponentGenerator($release),
                74 => new UninstallPluginGenerator($release),
                75 => new CreateSpatialReferenceGenerator($release),
                76 => new DropSpatialReferenceGenerator($release),
                77 => new AlterLogfileGroupGenerator($release),
                78 => new AlterTablespaceGenerator($release),
                79 => new AlterTablespaceAccessGenerator($release),
                80 => new AlterTablespaceDatafileGenerator($release),
                81 => new AlterUndoTablespaceGenerator($release),
                82 => new CreateLogfileGroupGenerator($release),
                83 => new CreateTablespaceGenerator($release),
                84 => new CreateUndoTablespaceGenerator($release),
                85 => new DropLogfileGroupGenerator($release),
                86 => new DropTablespaceGenerator($release),
                87 => new DropUndoTablespaceGenerator($release),
                88 => new RenameTablespaceGenerator($release),
                89 => new BeginGenerator($release),
                90 => new CommitGenerator($release),
                91 => new ReleaseSavepointGenerator($release),
                92 => new RollbackGenerator($release),
                93 => new RollbackToSavepointGenerator($release),
                94 => new SavepointGenerator($release),
                95 => new StartTransactionGenerator($release),
                96 => new XaCommitGenerator($release),
                97 => new XaEndGenerator($release),
                98 => new XaPrepareGenerator($release),
                99 => new XaRecoverGenerator($release),
                100 => new XaRollbackGenerator($release),
                101 => new XaStartGenerator($release),
                102 => new CreateTableLikeGenerator($release),
                103 => new DescribeTableGenerator($release),
                104 => new ExplainConnectionGenerator($release),
                105 => new HelpGenerator($release),
                106 => new UseDatabaseGenerator($release),
                107 => new SetTransactionGenerator($release),
                108 => new ShowCreateEventGenerator($release),
                109 => new ShowCreateFunctionGenerator($release),
                110 => new ShowCreateProcedureGenerator($release),
                111 => new ShowCreateTriggerGenerator($release),
                112 => new ShowFunctionCodeGenerator($release),
                113 => new ShowProcedureCodeGenerator($release),
                114 => new ShowBinaryLogStatusGenerator($release),
                115 => new ShowBinaryLogsGenerator($release),
                116 => new ShowBinlogEventsGenerator($release),
                117 => new ShowRelaylogEventsGenerator($release),
                118 => new ShowReplicaStatusGenerator($release),
                119 => new ShowReplicasGenerator($release),
                120 => new ShowCreateDatabaseGenerator($release),
                121 => new ShowCreateTableGenerator($release),
                122 => new ShowCreateViewGenerator($release),
                123 => new ShowCreateUserGenerator($release),
                124 => new ShowEngineCatalogGenerator($release),
                125 => new ShowEngineLogsGenerator($release),
                126 => new ShowEngineMutexGenerator($release),
                127 => new ShowEngineStatusGenerator($release),
                128 => new ShowGrantsGenerator($release),
                129 => new ShowPluginsGenerator($release),
                130 => new ShowPrivilegesGenerator($release),
                131 => new ShowProcesslistGenerator($release),
                132 => new ShowErrorCountGenerator($release),
                133 => new ShowErrorsGenerator($release),
                134 => new ShowProfileGenerator($release),
                135 => new ShowProfilesGenerator($release),
                136 => new ShowWarningCountGenerator($release),
                137 => new ShowWarningsGenerator($release),
                138 => new DropViewGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (Statement $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Statement>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Statement>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
