<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlObject;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\LogFileKind;
use SqlSemantics\Model\Statement\Definition\MySql\Storage as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds CREATE and ALTER LOGFILE GROUP with the log file each one adds.
 * @visibility SqlSemantics
 */
final class LogfileGroups
{
    /**
     * Reads the group name, the undo or legacy redo file, and the options of the form.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Origin $origin, Node $source, bool $create, Identifiers $identifiers): BoundStatement
    {
        $name = Tablespaces::name(Tree::outer($source, ['ident'])[0] ?? throw new UnclassifiedSql('A logfile group definition requires its name.'), $identifiers);
        $file = Tree::outer($source, ['lg_undofile', 'lg_redofile'])[0] ?? throw new UnclassifiedSql('A logfile group definition requires its log file.');
        $kind = $file->name === 'lg_redofile' ? LogFileKind::Redo : LogFileKind::Undo;
        $path = MySqlNames::read((Tree::outer($file, ['TEXT_STRING_sys'])[0] ?? throw new UnclassifiedSql('A log file requires its name.'))->tokens()[0], $identifiers);
        $clauses = StorageClauses::read($source, $identifiers);
        if ($create) {
            return new Statement\CreateLogfileGroupStatement($origin, $name, $kind, $path, $clauses->logfileGroup());
        }
        return new Statement\AlterLogfileGroupStatement($origin, $name, $kind, $path, $clauses->number('INITIAL_SIZE'), $clauses->engine, $clauses->waiting);
    }
}
