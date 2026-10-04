<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\DefaultPrivilegeChecks;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

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
 * @visibility public
 * @example Reading the action of the request
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES IN SCHEMA app GRANT SELECT ON TABLES TO PUBLIC');
 *     [$operation->statement->scopes[0]->option(), $operation->statement->action->objects->value] // => ['schemas', 'TABLES']
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
