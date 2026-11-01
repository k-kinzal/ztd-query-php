<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;

/**
 * Interprets column REFERENCES as foreign keys on MySQL 9.0 and later.
 * Earlier releases retain the syntax but do not install a constraint.
 * Verified through CREATE TABLE, SHOW CREATE TABLE and INSERT on all supported releases.
 *
 * @visibility MySqlMemory
 */
final class ColumnReferences
{
    /**
     * Answers the constraint declared by a table element, if the release enforces it.
     */
    public function key(TableElement $element, GrammarRelease $release): ?ForeignKey
    {
        if ($element instanceof ForeignKey) {
            return $element;
        }
        if (!$element instanceof ColumnDefinition || !in_array($release, [GrammarRelease::MySql901, GrammarRelease::MySql910], true)) {
            return null;
        }
        $specification = $element->specification;
        $references = $specification instanceof OrdinaryColumn || $specification instanceof GeneratedColumn ? $specification->references : null;

        return $references === null ? null : new ForeignKey([new ColumnPart($element->name->column)], $references);
    }
}
