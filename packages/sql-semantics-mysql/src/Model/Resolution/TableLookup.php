<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Resolution;

use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\ConflictingTables;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\TableResolution;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\UndeclaredTable;

/**
 * Resolves a relation name against the declarations of a context.
 *
 * Rule: CORE-TABLE-LOOKUP-001. Schemas are searched in path order; a qualified
 * name searches its schema only. Session-visible references select temporary
 * declarations first. Persistent renames prefer the permanent catalog.
 * Distinct declarations within the selected namespace conflict. No declaration is "missing" in a complete context and
 * "undeclared" in an open one. In an open context a declaration found after
 * the first searched schema is only a candidate, because an undeclared
 * relation of an earlier schema would be found first. Terminates: one pass
 * over the finite path. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class TableLookup
{
    /**
     * Resolves a relation name with the namespace precedence of the operation.
     */
    public function find(AnalysisContext $context, QualifiedName $name, bool $preferTemporary = true): TableResolution
    {
        $schemas = $name->schema === null ? $context->searchPath : [$name->schema];
        foreach ($schemas as $position => $schema) {
            $matches = $context->declared($name, $schema);
            if ($matches === []) {
                continue;
            }
            $preferred = array_values(array_filter($matches, static fn (Table $table): bool => $table->temporary === $preferTemporary));
            if ($preferred !== []) {
                $matches = $preferred;
            }
            if ($position > 0 && !$context->complete) {
                return new ConditionalTable($matches, new UndeclaredRelation($name));
            }

            return count($matches) === 1 ? new DeclaredTable($matches[0]) : new ConflictingTables($name, $matches);
        }

        return $context->complete ? new MissingTable($name) : new UndeclaredTable(new UndeclaredRelation($name));
    }
}
