<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW CREATE USER: the CREATE USER statement that reproduces an account (MySQL 5.7 and later).
 *
 * Rule: MYSQL-SHOW-CREATE-USER-001. One column, named `CREATE USER for
 * user@host` after the account written (host `%` when none is written);
 * for CURRENT_USER the name depends on the current user of the session and
 * the shape is open. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-user.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the column named after the account
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW CREATE USER u');
 *     [$show->field(0)->name?->value, $show->toString()] // => ['CREATE USER for u@%', 'SHOW CREATE USER u']
 */
final class ShowCreateUser implements Statement
{
    use Snapshot;

    /**
     * @param Account $user The account
     */
    public function __construct(public readonly Account $user)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $facts = new ShowFacts();
        if (!$this->user instanceof AccountName) {
            $derivation->output($facts->query($facts->open(new SessionState('the current user'))->shape, $derivation->context->columnNames));

            return;
        }
        $name = 'CREATE USER for ' . $this->user->user->value . '@' . ($this->user->host->value ?? '%');
        $derivation->output($facts->query($facts->shape($derivation, Report::CreateUser, $name), $derivation->context->columnNames));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'CREATE', 'USER')->node($this->user);
    }
}
