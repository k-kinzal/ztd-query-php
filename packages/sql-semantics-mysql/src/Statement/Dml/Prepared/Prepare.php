<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * PREPARE: names a statement whose text is given as a string or held by a user variable.
 *
 * Rule: MYSQL-PREPARE-001. The text is SQL the server parses when it runs
 * PREPARE; it is kept as the string it is, because the request is to
 * prepare that text, and a text held by a user variable is a session
 * value. A user variable is derived as a session value. The statement
 * returns no rows. Terminates: no nested statement is lowered. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/prepare.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a PREPARE
 *     $prepare = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("PREPARE stmt FROM 'SELECT ?'");
 *     [$prepare->statement->name->value, $prepare->statement->source->value, $prepare->toString()] // => ['stmt', 'SELECT ?', "PREPARE stmt FROM 'SELECT ?'"]
 */
final class Prepare implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The name of the prepared statement
     * @param Text|UserVariable $source The statement text, or the user variable that holds it
     */
    public function __construct(public readonly Name $name, public readonly Text|UserVariable $source)
    {
    }

    /**
     * Derives the user variable that holds the text, when there is one; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->source instanceof UserVariable) {
            $derivation->scalar($this->source, $derivation->environment());
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('PREPARE')->name($this->name, NameUse::Label)->keyword('FROM')->node($this->source);
    }
}
