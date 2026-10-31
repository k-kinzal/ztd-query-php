<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction;
use SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedBody;

/**
 * Reports intrinsic view-body refusals after preceding input checks.
 *
 * @visibility MySqlMemory
 */
final class ViewInput
{
    /**
     * Preserves definition identity and legacy clause order without reparsing its body.
     */
    public function error(RefusedBody $problem, Session $session): SqlError
    {
        if ($problem->definer instanceof \SqlSemantics\Platform\MySql\Statement\Name\AccountName) {
            (new \MySqlMemory\Command\Account\Names($session->settings()->release()))->check([$problem->definer]);
        }
        (new \MySqlMemory\Session\Problem\Identifiers())->check($problem->body, $session->settings()->release());
        if (($problem->view->schema->value ?? $session->variables->database) === '') {
            return QueryError::NoDatabase->error();
        }
        return match ($problem->restriction) {
            BodyRestriction::Variable => SchemaError::ViewSelectVariable->error(),
            BodyRestriction::Into => SchemaError::ViewSelectClause->error('INTO'),
        };
    }
}
