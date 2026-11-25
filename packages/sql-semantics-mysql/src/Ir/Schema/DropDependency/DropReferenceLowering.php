<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\DropDependency;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\AbsentDropTarget;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;

/**
 * Selects persistent native dependency edges for the checked table removal.
 * @visibility SqlSemantics
 */
final class DropReferenceLowering
{
    /**
     * Binds every incoming edge to the absolute selected persistent target.
     * @param list<TableIdentity|AbsentDropTarget> $targets Resolved native drop operands
     * @return list<TableDropReference>
     */
    public function resolve(AnalysisContext $context, array $targets): array
    {
        $references = [];
        foreach ($targets as $target) {
            if (!$target instanceof TableIdentity || $target->kind !== ExternalRelationKind::BaseTable) {
                continue;
            }
            Check::input($context->complete, 'Persistent DROP TABLE needs a complete catalog of referencing tables.');
            array_push($references, ...$this->incoming($context, $target));
        }

        return $references;
    }

    /**
     * Reads only native incoming edges that can name the selected parent.
     * @return list<TableDropReference>
     */
    public function incoming(AnalysisContext $context, TableIdentity $target): array
    {
        $references = [];
        foreach ($context->foreignKeys as $edge) {
            $schema = $edge->parent->schema?->value;
            if ($edge->parent->name->value !== $target->name->table->value
                || ($schema !== $target->name->schema->value && $schema !== SessionDatabase::UNNAMED)) {
                continue;
            }
            $childSchema = $edge->child->schema?->value;
            Check::input(
                $schema !== SessionDatabase::UNNAMED && $childSchema !== null && $childSchema !== SessionDatabase::UNNAMED,
                'The foreign-key dependency needs an absolute child and parent namespace.'
            );
            $name = new TableName(new Identifier($childSchema), new Identifier($edge->child->name->value));
            $references[] = new TableDropReference(new TableIdentity($name, ExternalRelationKind::BaseTable), $target);
        }

        return $references;
    }
}
