<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Facade\Index;

use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexDeclaration;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\StockIndexNames;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;

/**
 * Checks the shared namespace for a declaration's owner and its named index.
 *
 * @visibility SqlSemantics
 */
final class IndexCreationVisibility
{
    /**
     * Tests whether the exact name is already occupied at this declaration.
     *
     * @param list<IndexDeclaration> $indexes
     */
    public function occupied(AnalysisContext $context, array $indexes, Name $name, Name $schema): bool
    {
        foreach ($indexes as $existing) {
            if ($existing->schema->value === $schema->value && $existing->index->value === $name->value) {
                return true;
            }
        }

        return StockIndexNames::contains($context->profile->grammar, $schema, $name)
            || $context->declared(new QualifiedName($name), $schema) !== [];
    }

    /**
     * Requires the bound relation to be the first candidate when the definition omitted its schema.
     *
     * @param list<IndexDeclaration> $indexes
     * @throws InvalidConstruction When an earlier namespace can select a different relation
     */
    public function verifyPath(AnalysisContext $context, array $indexes, Name $table, Name $owner): void
    {
        foreach ($context->searchPath as $schema) {
            if ($schema->value === $owner->value) {
                return;
            }
            Check::input(
                !$this->occupied($context, $indexes, $table, $schema)
                && !$context->indexNamespace->hasUnknownNames($schema)
                && ($context->complete || IndexSchemas::system($schema->value)),
                'The indexed relation cannot skip an earlier unresolved namespace.',
            );
        }
        throw new InvalidConstruction('The indexed relation must belong to the searched namespace.');
    }
}
