<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\RoleChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a role, written as CREATE ROLE, CREATE USER or CREATE GROUP.
 *
 * Rule: PG-ROLE-CREATE-001. Mirrors `CreateRoleStmt`: the statement word
 * (`RoleStmtType`; CREATE USER makes LOGIN the default), the role name and
 * the options in the order written. The optional WITH before the options is
 * not kept. Reported: a name starting with `pg_`, an option filled twice, an
 * unrecognized option word, UNENCRYPTED PASSWORD, a connection limit below
 * -1 and PUBLIC in a role list (PG-ROLE-CHECK-001). A role is not a relation
 * declaration: the statement provides none.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html, https://www.postgresql.org/docs/17/sql-createuser.html,
 * https://www.postgresql.org/docs/17/sql-creategroup.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the options of a new role
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE USER joe WITH LOGIN ENCRYPTED PASSWORD 'secret'");
 *     [$operation->statement->name->value, $operation->statement->options[0]->option(), $operation->toString()] // => ['joe', 'canlogin', "CREATE USER joe login PASSWORD 'secret'"]
 */
final class CreateRole implements Statement
{
    use Snapshot;

    /**
     * @var list<RoleOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @param RoleWord $word The statement word: ROLE, USER or GROUP
     * @param Name $name The role name
     * @param list<RoleOption> $options The options in the order written
     */
    public function __construct(public readonly RoleWord $word, public readonly Name $name, array $options = [])
    {
        $this->options = Check::listOf($options, RoleOption::class, 'Role options are a list of role options.');
    }

    /**
     * Reports the problems of the name and of the options.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $checks = new RoleChecks();
        $checks->reserved($derivation, $this->name);
        $checks->options($derivation, $this->options);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', $this->word->value)->name($this->name, NameUse::Column);
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
