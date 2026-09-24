<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlObject;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Definition\Storage\RemovalOptions;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\TablespaceAccess;
use SqlSemantics\Model\Definition\Storage\UndoTablespaceState;
use SqlSemantics\Model\Statement\Definition\MySql\Storage as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds CREATE and ALTER for tablespaces and undo tablespaces in every MySQL release.
 * @visibility SqlSemantics
 */
final class Tablespaces
{
    /**
     * Selects the concrete form from the data-file action, rename, access mode, or undo keyword.
     * @param list<string> $words The leading keywords of the statement
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Origin $origin, Node $source, array $words, Identifiers $identifiers): BoundStatement
    {
        $names = Tree::outer($source, ['ident']);
        $name = self::name($names[0] ?? throw new UnclassifiedSql('A tablespace definition requires its name.'), $identifiers);
        if ($words[1] === 'UNDO') {
            $engine = RemovalOptions::engine($source, $identifiers);
            return $words[0] === 'CREATE'
                ? new Statement\CreateUndoTablespaceStatement($origin, $name, self::datafile($source, $identifiers) ?? throw new UnclassifiedSql('An undo tablespace requires its data file.'), $engine)
                : new Statement\AlterUndoTablespaceStatement($origin, $name, UndoTablespaceState::from(strtoupper(Tree::text(Tree::outer($source, ['undo_tablespace_state'])[0] ?? throw new UnclassifiedSql('An undo tablespace alteration requires its state.')))), $engine);
        }
        if ($words[0] === 'CREATE') {
            $group = Tree::outer($source, ['opt_logfile_group_name'])[0] ?? null;
            $group = $group === null ? null : Tree::outer($group, ['ident'])[0] ?? null;
            return new Statement\CreateTablespaceStatement($origin, $name, self::datafile($source, $identifiers), $group === null ? null : self::name($group, $identifiers), StorageClauses::read($source, $identifiers)->tablespace());
        }
        $access = Tree::outer($source, ['ts_access_mode'])[0] ?? null;
        if ($access !== null) {
            return new Statement\SetTablespaceAccessStatement($origin, $name, TablespaceAccess::from(strtoupper(implode(' ', array_map(static fn ($token): string => $token->text, $access->tokens())))));
        }
        if (($words[3] ?? '') === 'RENAME') {
            return new Statement\RenameTablespaceStatement($origin, $name, self::name($names[1] ?? throw new UnclassifiedSql('A tablespace rename requires its new name.'), $identifiers));
        }
        return self::alter($origin, $source, $words, $name, $identifiers);
    }

    /**
     * Binds the data-file alterations and the MySQL 8 property-only alteration.
     * @param list<string> $words The leading keywords of the statement
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function alter(Origin $origin, Node $source, array $words, string $name, Identifiers $identifiers): BoundStatement
    {
        $clauses = StorageClauses::read($source, $identifiers);
        $datafile = self::datafile($source, $identifiers);
        if ($datafile === null) {
            return new Statement\AlterTablespaceStatement($origin, $name, $clauses->changes());
        }
        return match ($words[3] ?? '') {
            'ADD' => new Statement\AddTablespaceDatafileStatement($origin, $name, $datafile, $clauses->changes()),
            'DROP' => new Statement\DropTablespaceDatafileStatement($origin, $name, $datafile, $clauses->changes()),
            default => new Statement\ChangeTablespaceDatafileStatement($origin, $name, $datafile, $clauses->number('INITIAL_SIZE'), $clauses->number('AUTOEXTEND_SIZE'), $clauses->number('MAX_SIZE')),
        };
    }

    /**
     * Reads a nonempty storage object name.
     * @throws InvalidSql
     */
    public static function name(Node $name, Identifiers $identifiers): string
    {
        $text = $identifiers->name($name->tokens()[0]);
        if ($text === '') {
            throw new InvalidSql(InputViolation::StorageName, $name);
        }
        return $text;
    }

    /**
     * Reads the data file named by DATAFILE, or null when the statement has none.
     */
    public static function datafile(Node $source, Identifiers $identifiers): ?string
    {
        $file = Tree::outer($source, ['ts_datafile'])[0] ?? null;
        $text = $file === null ? null : Tree::outer($file, ['TEXT_STRING_sys'])[0] ?? null;
        return $text === null ? null : MySqlNames::read($text->tokens()[0], $identifiers);
    }
}
