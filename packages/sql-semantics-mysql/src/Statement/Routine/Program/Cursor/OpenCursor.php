<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Routine\CursorFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;

/**
 * OPEN: opens a declared cursor.
 *
 * The facts follow MYSQL-PROGRAM-CURSORS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/open.html.
 *
 * @visibility SqlSemantics
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
