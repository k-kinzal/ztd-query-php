<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Routes PostgreSQL database, tablespace, schema, session and data-transfer utility commands.
 * @visibility SqlSemantics
 */
final class PostgreSqlCommands
{
    /**
     * Returns null for other dialects and for statements outside these utility families.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            return null;
        }
        return match ($statement->name) {
            'CreatedbStmt', 'AlterDatabaseStmt', 'AlterDatabaseSetStmt', 'DropdbStmt' => DatabaseCommands::bind($origin, $statement, $context),
            'CreateTableSpaceStmt', 'AlterTblSpcStmt', 'DropTableSpaceStmt' => TablespaceCommands::bind($origin, $statement, $context),
            'CreateSchemaStmt' => SchemaCreation::bind($origin, $statement, $context),
            'AlterSystemStmt' => SystemSettings::bind($origin, $statement, $context),
            'VariableShowStmt' => Session\SettingDisplays::bind($origin, $statement, $context),
            'ConstraintsSetStmt' => Session\ConstraintTimings::bind($origin, $statement, $context),
            'LoadStmt' => Session\CodeCommands::load($origin, $statement),
            'DoStmt' => Session\CodeCommands::block($origin, $statement, $context),
            'CallStmt' => Session\ProcedureCalls::bind($origin, $statement, $context),
            'VacuumStmt' => Maintenance\MaintenanceCommands::vacuum($origin, $statement, $context),
            'AnalyzeStmt', 'analyze_keyword' => Maintenance\MaintenanceCommands::analyze($origin, $statement, $context),
            'ClusterStmt' => Maintenance\MaintenanceCommands::cluster($origin, $statement, $context),
            'CopyStmt' => Transfer\CopyCommands::bind($origin, $statement, $context),
            default => null,
        };
    }
}
