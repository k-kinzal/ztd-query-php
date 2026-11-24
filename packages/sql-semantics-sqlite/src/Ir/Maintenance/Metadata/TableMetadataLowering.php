<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\MaintenanceLookup;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\PragmaScope;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;

/**
 * Resolves exact schema scopes and native table selections for metadata inspection.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableMetadataLowering
{
    /**
     * Publishes the requested metadata operation. A null inspection kind selects table_list.
     * @throws InvalidConstruction When the explicit schema or named target cannot be resolved
     */
    public function lower(BoundStatement $operation, Pragma $statement, ?TableInspectionKind $kind): TableListing|TableInspection
    {
        $value = $statement->value;
        $argument = $value === null ? null : new Identifier($value instanceof PragmaNumber ? $value->nativeText() : $value->value);
        $schema = (new PragmaScope())->resolve($operation->context, $statement->name->schema);
        if ($kind === null) {
            return new TableListing($schema, $argument);
        }

        $target = $argument === null ? null : $this->target($operation->context, $argument, $schema);

        return new TableInspection($kind, $target, $schema);
    }

    /**
     * Looks up a builtin catalog or the first eligible declaration.
     * @throws InvalidConstruction When a relation, its absence or its ordinary kind is unestablished
     */
    public function target(AnalysisContext $context, Identifier $argument, ?Identifier $schema): CatalogTable|RelationTarget|MissingMetadataTable
    {
        $catalog = (new CatalogAliases())->resolve($argument, $schema);
        if ($catalog !== null) {
            return $catalog;
        }
        $lookup = new MaintenanceLookup();
        $qualifier = $schema === null ? null : new Name($schema->value);
        $name = new QualifiedName(new Name($argument->value), $qualifier);
        $target = $lookup->relation($context, $name, $lookup->scopes($context, $qualifier));
        if ($target === null) {
            Check::input($context->complete, 'Metadata lookup cannot prove absence in an open namespace.');

            return new MissingMetadataTable($argument);
        }
        $owners = $context->declared($name, new Name($target->schema->value));
        Check::input($target->kind === ExternalRelationKind::View || $owners[0]->complete, 'Metadata inspection requires an ordinary table or view with a known kind.');

        return $target;
    }
}
