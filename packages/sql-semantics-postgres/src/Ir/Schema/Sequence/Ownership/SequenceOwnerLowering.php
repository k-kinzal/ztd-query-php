<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\StockIndexNames;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\TablePartitionRole;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * Resolves an automatic sequence dependency before publishing the declaration.
 *
 * @visibility SqlSemantics
 */
final class SequenceOwnerLowering
{
    /**
     * Fixes the table-like relation and exactly one declared column.
     * @throws InvalidConstruction When the resolved native relation cannot own a sequence
     */
    public function lower(AnalysisContext $context, DottedName $owner): ?SequenceOwner
    {
        $width = count($owner->parts);
        $column = $owner->last();
        if ($width === 1 && $column->value === 'none') {
            return null;
        }
        Check::input($width >= 2 && $width <= 4, 'Sequence ownership requires NONE or an exact relation column.');
        $reference = (new DottedName(array_slice($owner->parts, 0, -1)))->qualified();
        Check::input($reference !== null, 'Sequence ownership requires a local relation column.');
        Check::input(
            $reference->catalog === null || $reference->catalog->value === $context->currentDatabase?->value,
            'An explicit PostgreSQL catalog requires the matching current database.'
        );
        $relation = $this->find($context, $reference);
        $schema = $relation->name->schema ?? $context->declarationSchema;
        $fields = $relation->matchingColumns($column->value, Comparison::Sensitive);
        Check::input(count($fields) === 1, 'Sequence ownership requires one declared column.');
        $kind = match ($relation->kind) {
            RelationKind::BaseTable => $context->indexNamespace->partitionRole($schema, $relation->name->name) === TablePartitionRole::Branch
                ? SequenceOwnerKind::PartitionedTable : SequenceOwnerKind::BaseTable,
            RelationKind::ForeignTable => SequenceOwnerKind::ForeignTable,
            RelationKind::View => SequenceOwnerKind::View,
            RelationKind::MaterializedView, RelationKind::Sequence => throw new InvalidConstruction('Sequence ownership requires an eligible table-like relation.'),
        };

        return new SequenceOwner(
            new Identifier($schema->value),
            new Identifier($relation->name->name->value),
            new Identifier($fields[0]->name->value),
            $kind,
        );
    }

    /**
     * Selects the first definite native coordinate without skipping another relation kind.
     * @throws InvalidConstruction When the selector has no definite declaration
     */
    public function find(AnalysisContext $context, QualifiedName $reference): Table
    {
        foreach ($reference->schema === null ? $context->searchPath : [$reference->schema] as $schema) {
            $this->checkIndexes($context, $schema, $reference->name);
            $possible = $context->declared($reference, $schema);
            Check::input(count($possible) <= 1, 'Sequence ownership cannot resolve conflicting relation declarations.');
            if ($possible !== []) {
                return $possible[0];
            }
            Check::input(
                $context->complete && !$context->indexNamespace->hasUnknownNames($schema)
                && !StockIndexNames::contains($context->profile->grammar, $schema, $reference->name),
                'Sequence ownership requires a definite declared relation.'
            );
        }

        throw new InvalidConstruction('Sequence ownership requires a definite declared relation.');
    }

    /**
     * Rejects a native index match before looking at later namespaces.
     */
    public function checkIndexes(AnalysisContext $context, Name $schema, Name $owner): void
    {
        foreach ($context->indexNamespace->indexes as $index) {
            Check::input(
                $index->schema->value !== $schema->value || $index->index->value !== $owner->value,
                'Sequence ownership requires an eligible table-like relation.'
            );
        }
    }
}
