<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\MissingMetadataTable;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableMetadataLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\PragmaScope;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;

/**
 * Resolves the selector in a native integrity-check pragma.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class IntegrityAuditLowering
{
    /**
     * Binds the native scope and separates a report budget from a named target.
     * @throws InvalidConstruction When the requested relation or scope is unestablished
     */
    public function lower(BoundStatement $operation, Pragma $statement, IntegrityKind $kind): IntegrityAudit
    {
        $context = $operation->context;
        $scope = (new PragmaScope())->resolve($context, $statement->name->schema);
        $value = $statement->value;
        if ($value === null) {
            return new IntegrityAudit($kind, schema: $scope);
        }
        if ($value instanceof PragmaNumber) {
            $limit = (new IntegrityNumber())->limit($value->token);
            if ($limit !== null) {
                return new IntegrityAudit($kind, new IntegrityLimit($limit), $scope);
            }
        }
        $name = new Identifier($value instanceof PragmaNumber ? $value->nativeText() : $value->value);
        $target = (new TableMetadataLowering())->target($context, $name, $scope);
        Check::input(!$target instanceof MissingMetadataTable, 'Integrity audit requires a resolved table, view or schema catalog.');

        return new IntegrityAudit($kind, $target, $scope);
    }
}
