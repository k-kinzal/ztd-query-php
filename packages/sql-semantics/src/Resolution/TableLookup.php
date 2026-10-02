<?php

declare(strict_types=1);

namespace SqlSemantics\Resolution;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\ConflictingTables;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

/**
 * Resolves a relation name against the declarations of a context.
 *
 * Rule: CORE-TABLE-LOOKUP-001. Schemas are searched in path order; a qualified
 * name searches its schema only. One declaration resolves, several distinct
 * declarations conflict. No declaration is "missing" in a complete context and
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
     * Resolves a relation name; common tables are the caller's concern.
     */
    public function find(AnalysisContext $context, QualifiedName $name): TableResolution
    {
        $schemas = $name->schema === null ? $context->searchPath : [$name->schema];
        foreach ($schemas as $position => $schema) {
            $matches = $context->declared($name, $schema);
            if ($matches === []) {
                continue;
            }
            if ($position > 0 && !$context->complete) {
                return new ConditionalTable($matches, new UndeclaredRelation($name));
            }

            return count($matches) === 1 ? new DeclaredTable($matches[0]) : new ConflictingTables($name, $matches);
        }

        return $context->complete ? new MissingTable($name) : new UndeclaredTable(new UndeclaredRelation($name));
    }
}
