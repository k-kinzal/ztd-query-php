<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\CursorFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * FETCH: reads the next row of an open cursor into local variables.
 *
 * The optional words NEXT FROM and FROM carry no meaning and are not kept.
 * The facts follow MYSQL-PROGRAM-CURSORS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/fetch.html.
 *
 * @visibility public
 * @example Reading the targets of a FETCH
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p(OUT a INT) BEGIN DECLARE c CURSOR FOR SELECT 1; FETCH NEXT FROM c INTO a; END');
 *     [$create->statement->body->statements[0]->targets[0]->value, $create->toString()] // => ['a', 'CREATE PROCEDURE p(OUT a INT) BEGIN DECLARE c CURSOR FOR SELECT 1; FETCH c INTO a; END']
 */
final class FetchCursor implements ProgramStatement
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The variables that receive the row, in written order
     */
    public readonly array $targets;

    /**
     * @param Name $cursor The cursor name
     * @param list<Name> $targets The variables that receive the row; at least one
     */
    public function __construct(public readonly Name $cursor, array $targets)
    {
        $this->targets = Check::listOf($targets, Name::class, 'FETCH names at least one variable.', 1);
    }

    /**
     * Checks that the cursor and the variables are declared.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new CursorFacts())->cursor($this->cursor, $derivation, $scope);
        (new CursorFacts())->variables($this->targets, $derivation, $scope);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('FETCH')->name($this->cursor, NameUse::Identifier)->keyword('INTO');
        foreach ($this->targets as $position => $target) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($target, NameUse::Identifier);
        }
    }
}
