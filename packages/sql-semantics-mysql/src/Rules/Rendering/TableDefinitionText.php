<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Rendering;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\EnclosedQuery;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Writes a CREATE TABLE definition, for the executable statement and for its stored form alike.
 *
 * Rule: MYSQL-TABLE-DEFINITION-TEXT-001. The header, the element list in
 * parentheses when there is one, the options with their written commas, the
 * partitioning and the query follow in that order; the 5.x enclosed form writes
 * the partitioning inside the parenthesis that opens the query. Terminates: one
 * pass over the operands. Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableDefinitionText
{
    /**
     * Writes the definition from its operands.
     *
     * @param list<TableElement> $elements The column and key definitions
     * @param list<TableOption> $options The table options
     * @param list<bool> $optionsCommas The commas between adjacent options
     */
    public function render(Output $out, QualifiedName $name, int $temporaryWords, bool $ifNotExists, array $elements, array $options, array $optionsCommas, ?Partitioning $partitioning, ?TableQuery $query, bool $enclosed): void
    {
        $out->keyword('CREATE');
        for ($word = 0; $word < $temporaryWords; $word++) {
            $out->keyword('TEMPORARY');
        }
        $out->keyword('TABLE');
        if ($ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        if ($name->schema !== null) {
            $out->name($name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($name->name, NameUse::Relation);
        if ($enclosed && $partitioning !== null && $query !== null) {
            (new EnclosedQuery())->write($out, $query->query, $partitioning);

            return;
        }
        if ($elements !== []) {
            $out->symbol('(')->list($elements)->symbol(')');
        }
        (new OptionalCommas())->render($out, $options, $optionsCommas);
        $out->node($partitioning)->node($query);
    }
}
