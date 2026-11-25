<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Platform\MySql\Model\Contract\ForeignKeyEdge;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;

/**
 * Records the native persistent foreign keys supplied by one relation declaration.
 * @visibility SqlSemantics
 */
final class ForeignKeyDeclarations
{
    /**
     * Derives persistent dependency edges from the declaration and immutable grammar profile.
     * @return list<ForeignKeyEdge>
     */
    public function collect(BoundStatement $definition): array
    {
        $table = $definition->statement;
        if (!$table instanceof CreateTable || $table->temporary() || strcasecmp((new DeclaredTableStorage())->resolve($table), 'InnoDB') !== 0) {
            return [];
        }
        $schema = $table->name->schema ?? $definition->context->declarationSchema;
        $child = new QualifiedName($table->name->name, $schema);
        $inline = $this->inline($definition->context->profile->grammar);
        $foreignKeys = [];
        foreach ($table->elements as $element) {
            $reference = $this->reference($element, $inline);
            if ($reference !== null && ($element instanceof ForeignKey || $element instanceof ColumnDefinition)) {
                $fields = $this->childFields($element);
                $parent = new QualifiedName($reference->table->name, $reference->table->schema ?? $schema);
                $foreignKeys[] = new ForeignKeyEdge($child, $parent, $fields, $reference->columns);
            }
        }

        return $foreignKeys;
    }

    /**
     * Returns the ordered native child fields of a confirmed constraint declaration.
     * @return non-empty-list<Name>
     */
    public function childFields(ForeignKey|ColumnDefinition $element): array
    {
        return $element instanceof ForeignKey
            ? array_map(static fn (ColumnPart $part): Name => $part->column, $element->columns)
            : [$element->name->column];
    }

    /**
     * Determines whether column-level REFERENCES performs a native action.
     */
    public function inline(GrammarRelease $release): bool
    {
        return match ($release) {
            GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044,
            GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847 => false,
            GrammarRelease::MySql901, GrammarRelease::MySql910 => true,
        };
    }

    /**
     * Selects the reference node only when the declaration specifies a native constraint.
     */
    public function reference(TableElement $element, bool $inline): ?References
    {
        if ($element instanceof ForeignKey) {
            return $element->references;
        }

        return $inline && $element instanceof ColumnDefinition ? (new ElementFacts())->references($element->specification) : null;
    }
}
