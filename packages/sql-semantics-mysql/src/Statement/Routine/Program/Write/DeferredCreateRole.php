<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * An unconditional stored CREATE ROLE naming an anonymous account.
 *
 * A procedure can retain this instruction in an unreached branch. Invocation
 * checks every account in order; ordinary CREATE ROLE values exclude this domain.
 *
 * @visibility public
 * @example Retaining the complete account list until invocation
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() CREATE ROLE r, ''");
 *     count($operation->statement->body->roles) // => 2
 */
final class DeferredCreateRole implements ProgramStatement
{
    use Snapshot;

    /**
     * @var non-empty-list<AccountName> The complete account list in written order
     */
    public readonly array $roles;

    /**
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param list<AccountName> $roles The complete list, including an anonymous account
     */
    public function __construct(public readonly bool $ifNotExists, array $roles)
    {
        $this->roles = Check::listOf($roles, AccountName::class, 'CREATE ROLE names at least one account.', 1);
        Check::input(!$this->ifNotExists && array_filter($this->roles, static fn (AccountName $role): bool => $role->username() === '') !== [], 'A deferred role definition unconditionally creates an anonymous account.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Leaves account resolution and the anonymous-account condition at invocation.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
    }

    /**
     * Describes the retained definition without source SQL or parser nodes.
     */
    public function problem(): \SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedCreateRole
    {
        return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedCreateRole($this);
    }

    /**
     * Writes every account in the retained definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'ROLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->list($this->roles);
    }
}
