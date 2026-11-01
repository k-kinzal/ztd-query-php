<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Preparation;

use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Preparation;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Captures preparation alternatives at the SQL input boundary for both protocols.
 *
 * @visibility MySqlMemory
 */
final class Alternatives
{
    /**
     * Reads the original input once for every supported lexical interpretation.
     */
    public function read(string $sql, Session $session): Definition
    {
        $initial = new Interpretation($session->semantics()->profile(), Charset::known((string) $session->variables->read('character_set_client')));
        $database = $session->variables->database;
        return (new Preparation())->read($sql, $initial, $database === '' ? null : new Name($database));
    }
}
