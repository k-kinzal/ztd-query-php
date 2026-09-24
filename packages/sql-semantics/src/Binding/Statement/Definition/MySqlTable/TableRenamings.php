<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Definition\MySqlTable\TableRenaming;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Table\RenameTablesStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Schema\TableDefinition;

/**
 * Binds RENAME TABLE pairs, following tables renamed by earlier pairs of the same request.
 * @visibility SqlSemantics
 */
final class TableRenamings
{
    /**
     * Binds every pair in request order.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): RenameTablesStatement
    {
        $renamings = [];
        foreach (Tree::outer($source, ['table_to_table']) as $pair) {
            $names = Tree::outer($pair, ['table_ident']);
            if (count($names) !== 2) {
                throw new UnclassifiedSql('A table renaming requires a source and a target.');
            }
            $newName = new QualifiedName($context->tables->identifiers->parts($names[1]));
            $renamings[] = new TableRenaming(self::source($origin, $names[0], $renamings, $context), $newName);
        }
        if ($renamings === []) {
            throw new UnclassifiedSql('RENAME TABLE requires at least one pair.');
        }
        return new RenameTablesStatement($origin, $renamings);
    }

    /**
     * Resolves a source against earlier targets of the same request before the schema snapshot.
     * @param list<TableRenaming> $earlier Pairs already bound
     * @throws UnclassifiedSql
     */
    public static function source(Origin $origin, Node $name, array $earlier, QueryContext $context): TableReference
    {
        $parts = $context->tables->identifiers->parts($name);
        foreach (array_reverse($earlier) as $renaming) {
            if (self::same($parts, $renaming->newName->parts, $context)) {
                $moved = $renaming->table->declaration;
                $declaration = new TableDefinition(count($parts) === 2 ? $parts[0] : $context->tables->defaultSchema, $parts[count($parts) - 1], $moved->columns, $moved->constraints, $moved->source, $moved->resolved, $moved->indexes, $moved->properties);
                return new TableReference($context->ids->relation(), $origin->scopeId, $declaration, $context->tables->name($parts, $declaration), null, $name);
            }
        }
        $table = TableOccurrence::resolve($name, $context, $origin->scopeId);
        if (!$table instanceof TableReference) {
            throw new UnclassifiedSql('A renamed table requires a physical table occurrence.');
        }
        return $table;
    }

    /**
     * Compares two MySQL table names, reading an omitted database as the session default.
     * @param list<string> $left
     * @param list<string> $right
     */
    public static function same(array $left, array $right, QueryContext $context): bool
    {
        $default = $context->tables->defaultSchema;
        $leftSchema = count($left) === 2 ? $left[0] : $default;
        $rightSchema = count($right) === 2 ? $right[0] : $default;
        $identifiers = $context->tables->identifiers;
        return $identifiers->relationEqual($leftSchema, $rightSchema) && $identifiers->relationEqual($left[count($left) - 1] ?? '', $right[count($right) - 1] ?? '');
    }
}
