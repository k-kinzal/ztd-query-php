<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Access\DefaultPrivilegeChecks;

/**
 * A request to change the privileges that objects created in the future receive.
 *
 * Rule: PG-DEFAULT-PRIVILEGES-001. Mirrors `AlterDefaultPrivilegesStmt`:
 * the limiting clauses in the order written and one GRANT or REVOKE action.
 * Reported (PG-DEFAULT-PRIVILEGES-CHECK-001): a limiting clause given
 * twice, a column list, a privilege that does not apply to the kind of
 * object, IN SCHEMA together with ON SCHEMAS, PUBLIC as a role of FOR ROLE
 * and a grant option for PUBLIC.
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterDefaultPrivileges implements Statement
{
    use Snapshot;

    /**
     * @var list<DefaultScope> The limiting clauses in the order written
     */
    public readonly array $scopes;

    /**
     * @param list<DefaultScope> $scopes The limiting clauses in the order written
     * @param DefaultGrant|DefaultRevoke $action The GRANT or REVOKE action
     */
    public function __construct(array $scopes, public readonly DefaultGrant|DefaultRevoke $action)
    {
        $this->scopes = Check::listOf($scopes, DefaultScope::class, 'The limiting clauses are IN SCHEMA or FOR ROLE clauses.');
    }

    /**
     * Reports the problems of the clauses and of the action.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new DefaultPrivilegeChecks())->check($derivation, $this->scopes, $this->action);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'DEFAULT', 'PRIVILEGES');
        foreach ($this->scopes as $scope) {
            $out->node($scope);
        }
        $out->node($this->action);
    }
}
