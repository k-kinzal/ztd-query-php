<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `SET ROLE {DEFAULT | NONE | ALL [EXCEPT role, …] | role, …}` (8.0+): a request to change the active roles of the session.
 *
 * Mirrors PT_set_role. Rule: MYSQL-SET-ROLE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-role.html. Status: Implemented.
 *
 * @visibility public
 * @example Activating named roles
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('set role r1, r2')->toString() // => 'SET ROLE r1, r2'
 */
final class SetRole extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param RoleSelection $roles The roles to activate
     */
    public function __construct(public readonly RoleSelection $roles, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the account name conditions without resolving a runtime catalog.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->derive($derivation, [$this->roles]);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET', 'ROLE')->node($this->roles);
    }
}
