<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\RoleChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to set or reset the default of a configuration parameter for a role, for every role, or for them in one database.
 *
 * Rule: PG-ROLE-SET-001. Mirrors `AlterRoleSetStmt`: the role (none when
 * ALL is written), the database of IN DATABASE and the SET or RESET request
 * of the utility family, which is derived as the request it is. Reported:
 * PUBLIC or a `pg_` name as the role.
 * Source: https://www.postgresql.org/docs/17/sql-alterrole.html. Status: Implemented.
 *
 * @visibility public
 * @example Telling that the request is a statement of its own
 *     is_subclass_of(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterRoleSetting::class, \SqlSemantics\Statement\Statement::class) // => true
 */
final class AlterRoleSetting implements Statement
{
    use Snapshot;

    /**
     * @param RoleWord $word The statement word: ROLE or USER
     * @param RoleSpec|null $role The role; null when ALL is written
     * @param Name|null $database The database of IN DATABASE, when written
     * @param Statement $setting The SET or RESET request
     */
    public function __construct(public readonly RoleWord $word, public readonly ?RoleSpec $role, public readonly ?Name $database, public readonly Statement $setting)
    {
        Check::input($word !== RoleWord::Group, 'ALTER GROUP changes members only.');
    }

    /**
     * Reports the problems of the role and derives the SET or RESET request.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->role !== null) {
            (new RoleChecks())->alterable($derivation, $this->role);
        }
        $derivation->statement($this->setting);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', $this->word->value);
        if ($this->role === null) {
            $out->keyword('ALL');
        }
        $out->node($this->role);
        if ($this->database !== null) {
            $out->keyword('IN', 'DATABASE')->name($this->database, NameUse::Column);
        }
        $out->node($this->setting);
    }
}
