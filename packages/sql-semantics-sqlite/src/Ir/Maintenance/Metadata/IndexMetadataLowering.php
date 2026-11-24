<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget;
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
 * Resolves index metadata by native index precedence and primary-table storage.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class IndexMetadataLowering
{
    /**
     * Publishes the exact physical index or checked result of a missing lookup.
     * @throws InvalidConstruction When the required native namespace cannot be established
     */
    public function lower(BoundStatement $operation, Pragma $statement, IndexInspectionKind $kind): IndexInspection
    {
        $context = $operation->context;
        $scope = (new PragmaScope())->resolve($context, $statement->name->schema);
        $value = $statement->value;
        if ($value === null) {
            return new IndexInspection($kind, new EmptyIndexMetadata($scope));
        }
        $name = new Identifier($value instanceof PragmaNumber ? $value->nativeText() : $value->value);
        $qualified = new QualifiedName(new Name($name->value), $scope === null ? null : new Name($scope->value));
        $lookup = new MaintenanceLookup();
        $scopes = $lookup->scopes($context, $qualified->schema);
        $index = $lookup->index($context, $qualified, $scopes);
        if ($index !== null) {
            return new IndexInspection($kind, $index);
        }
        Check::input($context->complete, 'Index metadata cannot establish absence in an open namespace.');
        $table = $lookup->relation($context, $qualified, $scopes);
        if ($table === null) {
            $key = $name->key();
            Check::input(
                !str_starts_with($key, 'pragma_') && $key !== 'json_each' && $key !== 'json_tree',
                'Index metadata cannot establish a native module lookup from these declarations.'
            );
        }
        $storage = $table === null || $table->kind === ExternalRelationKind::View ? null : $this->storage($context, $table);

        return new IndexInspection($kind, $storage ?? new EmptyIndexMetadata($scope, $name));
    }

    /**
     * Finds the unique automatic index that stores the declared table's rows.
     * @throws InvalidConstruction When the storage role is ambiguous or hidden
     */
    public function storage(AnalysisContext $context, RelationTarget $table): ?IndexTarget
    {
        $name = new Name($table->name->value);
        $schema = new Name($table->schema->value);
        $owners = $context->declared(new QualifiedName($name, $schema), $schema);
        Check::input(count($owners) === 1 && $owners[0]->complete, 'Index metadata needs an ordinary declared storage layout.');
        $comparison = $context->relationNames;
        $result = null;
        foreach ($context->indexes as $index) {
            if ($index->primaryStorage && $comparison->equal($index->schema->value, $schema->value)
                && $comparison->equal($index->table->value, $name->value)) {
                Check::input($result === null, 'A table has at most one native primary storage index.');
                $result = new IndexTarget($table->schema, new Identifier($index->name->value), $table->name);
            }
        }
        Check::input(
            $result !== null || $owners[0]->implicit !== [] || $context->knowsConstraintIndexes($schema),
            'Index metadata cannot infer absence from an opaque primary storage namespace.'
        );

        return $result;
    }
}
