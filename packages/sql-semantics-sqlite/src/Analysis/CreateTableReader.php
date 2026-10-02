<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Schema\Definition\SqliteColumnDefinition;
use SqlSemantics\Statement\Schema\Definition\SqliteCreateTable;

/**
 * Reads an explicit table declaration without looking up or altering an existing table.
 * @visibility SqlSemantics
 */
final class CreateTableReader
{
    /**
     * Conditional creation always describes its own declaration, regardless of other context entries.
     */
    public function read(Node $source, \SqlSemantics\Statement\Contract\LanguageProfile $profile = new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472)): SqliteCreateTable
    {
        Tree::assertChildren($source, ['create_table', 'create_table_args'], []);
        $header = Tree::child($source, ['create_table']);
        $body = Tree::child($source, ['create_table_args']);
        assert($header !== null && $body !== null, 'A table declaration has a target and definition.');
        Tree::assertChildren($body, ['columnlist', 'table_option_set'], ['(', ')']);
        $columns = Tree::child($body, ['columnlist']);
        assert($columns !== null, 'An explicit table definition has columns.');
        $strict = false;
        foreach (Tree::outer($body, ['table_option']) as $option) {
            if (strtoupper(Tree::text($option)) !== 'STRICT') {
                Tree::unsupported($option, 'table storage option');
            }
            $strict = true;
        }
        return new SqliteCreateTable((new IdentifierReader())->qualified($header), Tree::child($header, ['temp']) !== null, Tree::child($header, ['ifnotexists']) !== null, $strict, $profile, ...$this->columns($columns, $strict));
    }

    /**
     * Column order and each original declaration object are retained.
     * @return non-empty-list<SqliteColumnDefinition>
     */
    public function columns(Node $source, bool $strict = false): array
    {
        Tree::assertChildren($source, ['columnlist', 'columnname', 'carglist'], [',']);
        $previous = Tree::child($source, ['columnlist']);
        $columns = $previous === null ? [] : $this->columns($previous, $strict);
        $column = Tree::child($source, ['columnname']);
        assert($column !== null, 'A column list item has a column declaration.');
        $columns[] = (new ColumnDefinitionReader())->read($column, Tree::child($source, ['carglist']), $strict);
        return $columns;
    }
}
