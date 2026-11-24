<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\StockIndexNames;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;

/**
 * Converts one native sequence selector to its definite occupancy.
 *
 * @visibility SqlSemantics
 */
final class SequenceTargetLowering
{
    /**
     * Resolves the first matching namespace or verifies an explicit absent role.
     */
    public function lower(AnalysisContext $context, QualifiedName $written, bool $guard): SequenceDropTarget
    {
        Check::input(
            $written->catalog === null || $written->catalog->value === $context->currentDatabase?->value,
            'An explicit PostgreSQL catalog requires the matching current database.'
        );
        $name = new Identifier($written->name->value);
        foreach ($written->schema === null ? $context->searchPath : [$written->schema] as $schema) {
            if ($this->present($context, $schema, $written)) {
                return new SequenceDropTarget($name, new Identifier($schema->value));
            }
            Check::input(
                $context->complete && !$context->indexNamespace->hasUnknownNames($schema),
                'A sequence selector cannot infer absence in an open relation namespace.'
            );
        }
        Check::input($guard, 'An absent sequence removal target requires IF EXISTS.');
        $scope = $written->schema;
        if ($scope === null) {
            return new SequenceDropTarget($name, null, SequenceDropPresence::MissingSequence);
        }
        $exists = (new IndexBinding())->known($context, $scope);
        Check::input($scope->value !== 'pg_temp' || $exists, 'An absent temporary sequence requires a declared session namespace.');

        return new SequenceDropTarget(
            $name,
            new Identifier($scope->value),
            $exists ? SequenceDropPresence::MissingSequence : SequenceDropPresence::MissingNamespace,
        );
    }

    /**
     * Checks a single namespace without skipping another kind of relation.
     */
    public function present(AnalysisContext $context, Name $schema, QualifiedName $written): bool
    {
        Check::input(
            !StockIndexNames::contains($context->profile->grammar, $schema, $written->name),
            'The selected relation is not a sequence.'
        );
        foreach ($context->indexNamespace->indexes as $index) {
            Check::input(
                $index->schema->value !== $schema->value || $index->index->value !== $written->name->value,
                'The selected relation is not a sequence.'
            );
        }
        $matches = $context->declared($written, $schema);
        Check::input(count($matches) <= 1, 'A sequence selector cannot resolve conflicting declarations.');
        Check::input($matches === [] || $matches[0]->kind === RelationKind::Sequence, 'The selected relation is not a sequence.');

        return $matches !== [];
    }
}
