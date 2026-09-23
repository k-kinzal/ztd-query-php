<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\AllForeignTables;
use SqlSemantics\Model\Definition\Foreign\ExcludeForeignTables;
use SqlSemantics\Model\Definition\Foreign\ImportOnlyTables;
use SqlSemantics\Model\Statement\Definition\PostgreSql\ImportForeignSchemaStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Separates import endpoints, remote relation selection, and wrapper options.
 * @visibility SqlSemantics
 */
final class ForeignImports
{
    /**
     * Binds remote identifiers without incorrectly resolving them as local tables.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?ImportForeignSchemaStatement
    {
        if ($origin->dialect !== Dialect::PostgreSql || $source->name !== 'ImportForeignSchemaStmt') {
            return null;
        }
        $names = Tree::outer($source, ['name']);
        if (count($names) !== 3) {
            throw new UnclassifiedSql('A foreign schema import requires remote schema, server, and local schema names.');
        }
        $identifiers = $context->tables->identifiers;
        $qualification = Tree::child($source, ['import_qualification']);
        $tables = $qualification === null ? [] : array_map(static fn (Node $node) => ForeignOperands::relation($node, $identifiers), Tree::outer($qualification, ['relation_expr']));
        $selection = $qualification === null ? AllForeignTables::InSchema : (strtoupper($qualification->tokens()[0]->text) === 'LIMIT' ? new ImportOnlyTables($tables) : new ExcludeForeignTables($tables));
        $options = array_map(static fn (Node $node) => ForeignOperands::option($node, $identifiers), Tree::outer($source, ['generic_option_elem']));
        return new ImportForeignSchemaStatement($origin, $identifiers->name($names[0]->tokens()[0]), $identifiers->name($names[1]->tokens()[0]), $identifiers->name($names[2]->tokens()[0]), $selection, $options);
    }
}
