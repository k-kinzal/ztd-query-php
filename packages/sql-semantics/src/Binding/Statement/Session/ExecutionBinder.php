<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Routes session operations, execution plans and named schema commands.
 * @visibility SqlSemantics
 */
final class ExecutionBinder
{
    /**
     * Returns the first operation whose grammar belongs to an explicit command family.
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        $binders = [
            static fn (): ?BoundStatement => Statement\Utility\PostgreSqlCommands::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\Definition\DefinitionBinder::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\Maintenance\IndexCaches::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\Maintenance\MySqlTables::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\Maintenance\TruncateBinder::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => TableLockBinder::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\Procedural\ProceduralCommands::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\Inspection\ServerInspection::bind($origin, $statement),
            static fn (): ?BoundStatement => Statement\Inspection\SchemaInspection::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => Statement\Inspection\SessionInspection::bind($origin, $statement, $context),
            static fn (): ?BoundStatement => SessionCommands::bind($origin, $statement, $context),
        ];
        foreach ($binders as $binder) {
            $bound = $binder();
            if ($bound !== null) {
                return $bound;
            }
        }
        return null;
    }
}
