<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry\Event;

use MySqlMemory\Command\Account\Names;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Syntax;
use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Formats an incomplete event alteration after checking its typed header.
 *
 * @visibility MySqlMemory\Session\Parse
 */
final class Alteration
{
    /**
     * @throws SqlError When a preceding account or default-database constraint fails
     */
    public function error(EmptyEventAlteration $problem, Session $session): SqlError
    {
        (new Names($session->settings()->release()))->check([$problem->definer]);
        if ($problem->name->schema === null && $session->variables->database === '') {
            throw QueryError::NoDatabase->error();
        }

        return $this->syntax($problem, $session);
    }

    /**
     * Resolves the retained header before a schedule operand condition.
     */
    public function operand(\SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand $problem, Session $session): SqlError
    {
        (new Names($session->settings()->release()))->check([$problem->definer]);
        if ($problem->name->schema === null && $session->variables->database === '') {
            throw QueryError::NoDatabase->error();
        }
        if (!$problem->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth) {
            return (new \MySqlMemory\Session\Problem\Errors())->error($problem->problem, $session);
        }
        return (new OperandConditions())->error($problem->problem, $session->settings()->legacy());
    }

    /**
     * Formats the refusal after any enclosing program header has supplied its database.
     */
    public function syntax(EmptyEventAlteration $problem, Session $session): SqlError
    {
        return (new Syntax(Charset::named((string) $session->variables->read('character_set_client'))))->near($problem->offset, $session->text, $session->following, new RuntimeException($problem->message()), true, $session->semantics()->parser());
    }
}
