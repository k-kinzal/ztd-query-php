<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Routine\CursorFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * OPEN: opens a declared cursor.
 *
 * The facts follow MYSQL-PROGRAM-CURSORS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/open.html.
 *
 * @visibility public
 * @example Reading the cursor an OPEN names
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; OPEN c; END');
 *     [$create->statement->body->statements[0]->cursor->value, $create->facts->diagnostics] // => ['c', []]
 */
final class OpenCursor implements ProgramStatement
{
    use Snapshot;

    /**
     * @param Name $cursor The cursor name
     */
    public function __construct(public readonly Name $cursor)
    {
    }

    /**
     * Checks that the cursor is declared.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new CursorFacts())->cursor($this->cursor, $derivation, $scope);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('OPEN')->name($this->cursor, NameUse::Identifier);
    }
}
