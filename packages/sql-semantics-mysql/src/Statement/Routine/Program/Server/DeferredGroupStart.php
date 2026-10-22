<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\GroupCredentials;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedGroupStart;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored group start whose explicit credentials conflict independently of server state.
 *
 * The complete option list remains available until the instruction is reached.
 * This is a program instruction, not an ordinary standalone statement.
 *
 * @visibility public
 * @example Retaining the credential condition until invocation
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() START GROUP_REPLICATION PASSWORD = 'p'");
 *     $operation->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart // => true
 */
final class DeferredGroupStart implements ProgramStatement
{
    use Snapshot;

    /**
     * @var non-empty-list<CredentialOption> The complete ordered credentials
     */
    public readonly array $options;

    /**
     * @param list<CredentialOption> $options The complete ordered credentials
     */
    public function __construct(array $options)
    {
        $this->options = Check::listOf($options, CredentialOption::class, 'A deferred group start retains credentials.', 1);
        Check::input((new GroupCredentials())->problem($this->options) !== null, 'A deferred group start retains an intrinsic credential condition.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Leaves credential validation until control reaches this instruction.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
    }

    /**
     * Describes the retained condition using semantic values only.
     */
    public function problem(): RefusedGroupStart
    {
        return new RefusedGroupStart($this);
    }

    /**
     * Writes every credential in its original order.
     */
    public function render(Output $out): void
    {
        $out->keyword('START', 'GROUP_REPLICATION')->list($this->options);
    }
}
