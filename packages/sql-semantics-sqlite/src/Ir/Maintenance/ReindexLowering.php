<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Reindex as ParsedReindex;

/**
 * Resolves native SQLite rebuild selection using the collation, relation and index namespaces.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ReindexLowering
{
    /**
     * Publishes a resolved rebuild or refuses an unestablished named selector.
     *
     * @throws InvalidConstruction When native name precedence cannot be established
     */
    public function lower(BoundStatement $operation, ParsedReindex $statement): Reindex
    {
        $target = $statement->target;
        if ($target === null) {
            return new Reindex();
        }
        if ($target->schema === null) {
            $collation = Collation::tryFrom(strtr($target->name->value, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
            if ($collation !== null) {
                return new Reindex($collation);
            }
        }
        $context = $operation->context;
        $scopes = $this->scopes($context, $target->schema);
        $relation = $this->relation($context, $target, $scopes);

        return new Reindex($relation ?? $this->index($context, $target->name, $scopes));
    }

    /**
     * Fixes the selected namespaces in native precedence order.
     *
     * @return non-empty-list<Name>
     * @throws InvalidConstruction When the schema is unknown or the comparison differs
     */
    public function scopes(AnalysisContext $context, ?Name $schema): array
    {
        $comparison = Comparison::AsciiInsensitive;
        $path = $context->searchPath;
        Check::input(
            $context->relationNames === $comparison && isset($path[1])
                && $comparison->equal($path[0]->value, 'temp') && $comparison->equal($path[1]->value, 'main'),
            'REINDEX requires SQLite comparison and schema search order.',
        );
        if ($schema === null) {
            return $path;
        }
        foreach ($path as $known) {
            if ($comparison->equal($known->value, $schema->value)) {
                return [$known];
            }
        }

        throw new InvalidConstruction('REINDEX requires a known schema qualifier.');
    }

    /**
     * Looks for the native relation before any index can win the selector.
     *
     * @param non-empty-list<Name> $scopes
     * @throws InvalidConstruction When a relation match or preceding scope is ambiguous
     */
    public function relation(AnalysisContext $context, QualifiedName $name, array $scopes): ?ReindexRelation
    {
        foreach ($scopes as $schema) {
            $matches = $context->declared($name, $schema);
            Check::input(count($matches) <= 1, 'Conflicting declared relations share the REINDEX coordinate.');
            if ($matches !== []) {
                $table = $matches[0];
                $kind = match ($table->kind) {
                    RelationKind::BaseTable => ExternalRelationKind::BaseTable,
                    RelationKind::View => ExternalRelationKind::View,
                    RelationKind::ForeignTable, RelationKind::MaterializedView, RelationKind::Sequence
                        => throw new InvalidConstruction('The REINDEX relation kind is outside the SQLite profile.'),
                };

                return new ReindexRelation(new Identifier($schema->value), new Identifier($table->name->name->value), $kind);
            }
            Check::input($name->schema !== null || $context->complete, 'Unqualified REINDEX cannot skip an open relation namespace.');
        }

        return null;
    }

    /**
     * Finds the first matching index once relation precedence has been established.
     *
     * @param non-empty-list<Name> $scopes
     * @throws InvalidConstruction When no index with a proven coordinate exists
     */
    public function index(AnalysisContext $context, Name $name, array $scopes): ReindexIndex
    {
        $key = new Identifier($name->value);
        foreach ($scopes as $schema) {
            $matches = $context->indexesAt($name, $schema);
            Check::input(count($matches) <= 1, 'Conflicting declared indexes share the REINDEX coordinate.');
            if ($matches !== []) {
                $target = $matches[0];
                return new ReindexIndex(
                    new Identifier($target->schema->value),
                    new Identifier($target->name->value),
                    new Identifier($target->table->value),
                );
            }
            Check::input(
                !str_starts_with($key->key(), 'sqlite_') || $context->knowsConstraintIndexes($schema),
                'REINDEX cannot skip an opaque constraint index namespace.'
            );
        }

        throw new InvalidConstruction('REINDEX requires a resolved collation, relation or index.');
    }
}
