<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Storage;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Storage as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds separate tablespace, undo-tablespace, and logfile-group removal operations.
 * @visibility SqlSemantics
 */
final class Removals
{
    /**
     * Describes storage targets without touching files or expanding engine effects.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $tokens = $source->tokens();
        $kind = strtoupper($tokens[1]->text ?? '');
        if ($origin->dialect !== Dialect::MySql || strtoupper($tokens[0]->text ?? '') !== 'DROP' || !in_array($kind, ['TABLESPACE', 'LOGFILE', 'UNDO'], true)) {
            return null;
        }
        $name = Tree::child($source, ['ident', 'tablespace_name', 'logfile_group_name']) ?? throw new UnclassifiedSql('Storage removal requires its target name.');
        $identity = $context->tables->identifiers->name($name->tokens()[0]);
        if ($identity === '') {
            throw new InvalidSql(InputViolation::StorageName, $name);
        }
        $engine = RemovalOptions::engine($source, $context->tables->identifiers);
        $waiting = RemovalOptions::waiting($source);
        return match ($kind) {
            'TABLESPACE' => new Statement\DropTablespaceStatement($origin, $identity, $engine, $waiting),
            'LOGFILE' => new Statement\DropLogfileGroupStatement($origin, $identity, $engine, $waiting),
            'UNDO' => new Statement\DropUndoTablespaceStatement($origin, $identity, $engine),
        };
    }
}
