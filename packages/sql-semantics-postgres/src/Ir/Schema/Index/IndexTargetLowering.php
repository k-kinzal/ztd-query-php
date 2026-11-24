<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionIndexReference;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionRebuildKind;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexDeclaration;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexDeclarationKind;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;

/**
 * Discharges one index selector using the fixed relation path.
 *
 * @visibility SqlSemantics
 */
final class IndexTargetLowering
{
    /**
     * Answers the resolved index or typed absence for one target selector.
     */
    public function resolve(AnalysisContext $context, QualifiedName $name, bool $optional): IndexReference|MissingIndex
    {
        $match = $this->lookup($context, $name);
        if ($match !== null) {
            Check::input($match->kind === IndexDeclarationKind::Ordinary, 'The selected index is protected in the ordinary index profile.');

            return new IndexReference(new Identifier($match->schema->value), new Identifier($match->index->value), new Identifier($match->table->value));
        }
        Check::input($optional, 'The index selector requires a declared index.');
        $schema = $name->schema;

        return new MissingIndex(
            new Identifier($name->name->value),
            $schema === null ? null : new Identifier($schema->value),
            $schema !== null && !(new IndexBinding())->known($context, $schema),
        );
    }

    /**
     * Identifies the checked declared physical or partition-index rebuild role.
     * @throws InvalidConstruction For an index outside the supported rebuild roles
     */
    public function resolveRebuild(AnalysisContext $context, QualifiedName $name): IndexReference|ConstraintIndexReference|ExclusionIndexReference|PartitionIndexReference
    {
        $match = $this->lookup($context, $name);
        Check::input($match !== null, 'The index selector requires a declared index.');
        $schema = new Identifier($match->schema->value);
        $index = new Identifier($match->index->value);
        $table = new Identifier($match->table->value);

        return match ($match->kind) {
            IndexDeclarationKind::Ordinary => new IndexReference($schema, $index, $table),
            IndexDeclarationKind::KeyConstraint => new ConstraintIndexReference($schema, $index, $table),
            IndexDeclarationKind::PartitionBranch => new PartitionIndexReference($schema, $index, $table, PartitionRebuildKind::Branch),
            IndexDeclarationKind::PartitionLeaf => new PartitionIndexReference($schema, $index, $table, PartitionRebuildKind::Leaf),
            IndexDeclarationKind::Partitioned => throw new InvalidConstruction('This index rebuild requires a nonpartitioned index.'),
            IndexDeclarationKind::ExclusionConstraint => new ExclusionIndexReference($schema, $index, $table),
        };
    }

    /**
     * Finds the exact declared index at the first definite namespace coordinate.
     */
    public function lookup(AnalysisContext $context, QualifiedName $name): ?IndexDeclaration
    {
        Check::input(
            $name->catalog === null || $name->catalog->value === $context->currentDatabase?->value,
            'An explicit PostgreSQL catalog requires the matching current database.',
        );
        $binding = new IndexBinding();
        $schemas = $name->schema === null ? $context->searchPath : [$name->schema];
        foreach ($schemas as $schema) {
            $match = $binding->find($context, $schema, $name);
            if ($match !== null) {
                return $match;
            }
            Check::input(
                !$context->indexNamespace->hasUnknownNames($schema)
                && ($context->complete || IndexSchemas::system($schema->value)),
                'The index selector cannot infer absence in an open index namespace.',
            );
        }

        return null;
    }

}
