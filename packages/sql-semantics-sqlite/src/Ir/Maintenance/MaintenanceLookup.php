<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;

/**
 * Resolves absolute SQLite maintenance targets from an explicit catalog.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class MaintenanceLookup
{
    /**
     * Fixes the selected namespaces in native precedence order.
     *
     * @return non-empty-list<Name>
     * @throws InvalidConstruction When the schema is unknown or the comparison differs
     */
    public function scopes(AnalysisContext $context, ?Name $schema): array
    {
        $path = $this->path($context);
        if ($schema === null) {
            return $path;
        }
        $known = $this->schema($schema, $path);
        Check::input($known !== null, 'The maintenance statement requires a known schema qualifier.');

        return [$known];
    }

    /**
     * Looks for the native relation before any index can win the selector.
     *
     * @param non-empty-list<Name> $scopes
     * @throws InvalidConstruction When a relation match or preceding scope is ambiguous
     */
    public function relation(AnalysisContext $context, QualifiedName $name, array $scopes): ?RelationTarget
    {
        foreach ($scopes as $schema) {
            $matches = $context->declared($name, $schema);
            Check::input(count($matches) <= 1, 'Conflicting declared relations share the maintenance coordinate.');
            if ($matches !== []) {
                $table = $matches[0];
                $kind = match ($table->kind) {
                    RelationKind::BaseTable => ExternalRelationKind::BaseTable,
                    RelationKind::View => ExternalRelationKind::View,
                    RelationKind::ForeignTable, RelationKind::MaterializedView, RelationKind::Sequence
                        => throw new InvalidConstruction('The maintenance relation kind is outside the SQLite profile.'),
                };

                return new RelationTarget(new Identifier($schema->value), new Identifier($table->name->name->value), $kind);
            }
            Check::input($name->schema !== null || $context->complete, 'An unqualified selector cannot skip an open relation namespace.');
        }

        return null;
    }

    /**
     * Finds the first matching index in the selected SQLite scope.
     *
     * @param non-empty-list<Name> $scopes
     * @throws InvalidConstruction When no index with a proven coordinate exists
     */
    public function index(AnalysisContext $context, QualifiedName $name, array $scopes): ?IndexTarget
    {
        $key = new Identifier($name->name->value);
        foreach ($scopes as $schema) {
            $matches = $context->indexesAt($name->name, $schema);
            Check::input(count($matches) <= 1, 'Conflicting declared indexes share the maintenance coordinate.');
            if ($matches !== []) {
                $target = $matches[0];
                return new IndexTarget(
                    new Identifier($target->schema->value),
                    new Identifier($target->name->value),
                    new Identifier($target->table->value),
                );
            }
            Check::input(
                !str_starts_with($key->key(), 'sqlite_') || $context->knowsConstraintIndexes($schema),
                'A maintenance selector cannot skip an opaque constraint index namespace.'
            );
            Check::input($name->schema !== null || $context->complete, 'An unqualified selector cannot skip an open index namespace.');
        }

        return null;
    }

    /**
     * Verifies native SQLite schema comparison and search order.
     *
     * @return non-empty-list<Name>
     */
    public function path(AnalysisContext $context): array
    {
        $comparison = Comparison::AsciiInsensitive;
        $path = $context->searchPath;
        Check::input(
            $context->relationNames === $comparison && isset($path[1])
                && $comparison->equal($path[0]->value, 'temp') && $comparison->equal($path[1]->value, 'main'),
            'A maintenance statement requires SQLite comparison and schema search order.',
        );

        return $path;
    }

    /**
     * Resolves an explicitly named schema before looking for its objects.
     *
     * @param non-empty-list<Name> $path
     */
    public function schema(Name $name, array $path): ?Name
    {
        $comparison = Comparison::AsciiInsensitive;
        foreach ($path as $known) {
            if ($comparison->equal($known->value, $name->value)) {
                return $known;
            }
        }

        return null;
    }
}
