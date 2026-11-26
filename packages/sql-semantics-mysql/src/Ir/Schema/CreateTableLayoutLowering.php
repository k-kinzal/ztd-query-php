<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Key\CreatePrimaryKeyLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryColumnPolicy;
use SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnFlags;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;

/**
 * Collects the owning columns before binding their primary-key references.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateTableLayoutLowering
{
    /**
     * Builds the checked layout and its optional resolved key.
     *
     * @return array{list<TableColumn>, ?PrimaryKey}
     */
    public function lower(ParsedCreateTable $statement, GrammarRelease $release, ?Name $collation): array
    {
        $positions = [];
        $definitions = [];
        $inline = null;
        $index = null;
        $flags = new ColumnFlags();
        foreach ($statement->elements as $element) {
            if ($element instanceof ColumnDefinition) {
                $name = new Identifier($element->name->column->value);
                $key = $name->key();
                $position = count($definitions);
                Check::input(!isset($positions[$key]), 'CREATE TABLE cannot repeat a column name.');
                $positions[$key] = $position;
                $definitions[] = $element;
                if ($flags->flags($element->specification)[2]) {
                    Check::input($inline === null, 'CREATE TABLE requires at most one primary key.');
                    $inline = $position;
                }
            } else {
                Check::input($element instanceof IndexDefinition && $index === null, 'CREATE TABLE requires ordinary columns and at most one primary key.');
                $index = $element;
            }
        }
        $primary = (new CreatePrimaryKeyLowering())->lower($index, $inline, $positions, $release);
        $members = [];
        foreach ($primary->terms ?? [] as $term) {
            $members[$term->slot] = true;
        }
        $policy = new PrimaryColumnPolicy($release, $inline !== null);
        $columns = [];
        $lowering = new CreateColumnLowering();
        foreach ($definitions as $slot => $column) {
            $columns[] = $lowering->lower($column, $collation, isset($members[$slot]) ? $policy : null);
        }

        return [$columns, $primary];
    }
}
