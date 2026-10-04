<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW GRANTS: the GRANT statements that reproduce the privileges of an account or role.
 *
 * Rule: MYSQL-SHOW-GRANTS-001. One column, named `Grants for user@host`
 * after the account written (host `%` when none is written). Without FOR,
 * or with FOR CURRENT_USER, the account is the current user of the session,
 * so the column name depends on it and the shape is open. USING (8.0 and
 * later) names roles whose privileges are shown as granted to the account.
 * Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-grants.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the column named after the account
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW GRANTS FOR 'u'@'localhost' USING r");
 *     [$show->field(0)->name?->value, $show->toString()] // => ['Grants for u@localhost', 'SHOW GRANTS FOR u@localhost USING r']
 */
final class ShowGrants implements Statement
{
    use Snapshot;

    /**
     * @var list<Account> The roles of USING in order
     */
    public readonly array $using;

    /**
     * @param Account|null $user The account or role after FOR; null when FOR is not written
     * @param list<Account> $using The roles of USING in order; only with an account
     */
    public function __construct(public readonly ?Account $user = null, array $using = [])
    {
        $this->using = Check::listOf($using, Account::class, 'USING names a list of roles.');
        Check::input($user !== null || $this->using === [], 'USING requires FOR.');
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
        $name = 'Grants for ' . $this->user->user->value . '@' . ($this->user->host->value ?? '%');
        $derivation->output($facts->query($facts->shape($derivation, Report::Grants, $name), $derivation->context->columnNames));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'GRANTS');
        if ($this->user !== null) {
            $out->keyword('FOR')->node($this->user);
        }
        if ($this->using !== []) {
            $out->keyword('USING')->list($this->using);
        }
    }
}
