<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\DropColumn;
use SqlSemantics\Statement\Schema\DropIndex;
use SqlSemantics\Statement\Schema\DropTable;
use SqlSemantics\Statement\Schema\DropTrigger;
use SqlSemantics\Statement\Schema\DropView;
use SqlSemantics\Statement\Schema\RenameColumn;
use SqlSemantics\Statement\Schema\RenameTable;

/**
 * Structures removal and rename requests without executing schema changes.
 * @visibility SqlSemantics
 */
final class SchemaChangeReader
{
    /**
     * Retains the supplied declaration identities even when the request changes names.
     */
    public function read(Node $command, Catalog $catalog): DropTable|DropView|DropIndex|DropTrigger|RenameTable|RenameColumn|DropColumn
    {
        $source = Tree::child($command, ['fullname']);
        assert($source !== null, 'A removal or rename command identifies its target.');
        $reader = new IdentifierReader();
        $name = $reader->qualified($source);
        $tokens = array_values(array_filter($command->children, static fn (Node|Token $child): bool => $child instanceof Token));
        $keywords = array_map(static fn (Token $token): string => strtoupper($token->text), $tokens);
        $conditional = Tree::child($command, ['ifexists']) !== null;
        $table = new TableReference($catalog, $name);
        if ($keywords[0] === 'DROP') {
            return match ($keywords[1]) {
                'TABLE' => new DropTable($table, $conditional),
                'VIEW' => new DropView($table, $conditional),
                'INDEX' => new DropIndex($name, $conditional),
                'TRIGGER' => new DropTrigger($name, $conditional),
                default => Tree::unsupported($command, 'removal target'),
            };
        }
        assert($keywords[0] === 'ALTER' && $keywords[1] === 'TABLE', 'A rename or column removal targets a table.');
        $names = $reader->directNames($command);
        $explicitColumn = Tree::child($command, ['kwcolumn_opt']) !== null;
        if ($keywords[2] === 'DROP') {
            assert(count($names) === 1, 'Column removal has one column target.');
            return new DropColumn($table, $names[0], $explicitColumn);
        }
        assert($keywords[2] === 'RENAME', 'A remaining schema change is a rename.');
        assert(count($names) === 1 || count($names) === 2, 'A rename identifies a new name or an old and new column name.');
        return count($names) === 1 ? new RenameTable($table, $names[0]) : new RenameColumn($table, $names[0], $names[1], $explicitColumn);
    }
}
