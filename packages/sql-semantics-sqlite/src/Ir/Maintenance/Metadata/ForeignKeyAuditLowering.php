<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\PragmaScope;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;

/**
 * Resolves SQLite foreign-key audit scopes before publication.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ForeignKeyAuditLowering
{
    /**
     * Binds a single resolved relation or the selected database schema.
     * @throws InvalidConstruction When the requested child or scope cannot be established
     */
    public function lower(BoundStatement $operation, Pragma $statement): ForeignKeyAudit
    {
        $context = $operation->context;
        $scope = (new PragmaScope())->resolve($context, $statement->name->schema);
        $value = $statement->value;
        if ($value === null) {
            return new ForeignKeyAudit($scope ?? new Identifier('main'));
        }
        $name = new Identifier($value instanceof PragmaNumber ? $value->nativeText() : $value->value);
        $target = (new TableMetadataLowering())->target($context, $name, $scope);
        Check::input(!$target instanceof MissingMetadataTable, 'Foreign-key audit requires a resolved child or schema catalog.');

        return new ForeignKeyAudit($target, $target instanceof CatalogTable ? $scope : null);
    }
}
