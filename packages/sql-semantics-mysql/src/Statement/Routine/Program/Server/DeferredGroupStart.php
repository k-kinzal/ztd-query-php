<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\GroupCredentials;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;

/**
 * A stored group start whose explicit credentials conflict independently of server state.
 *
 * The complete option list remains available until the instruction is reached.
 * This is a program instruction, not an ordinary standalone statement.
 *
 * @visibility public
 * @example Retaining the credential condition until invocation
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() START GROUP_REPLICATION PASSWORD = 'p'");
 *     $operation->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart // => true
 */
final class DeferredGroupStart implements ProgramStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<CredentialOption> The complete ordered credentials
     */
    public readonly array $options;

    /**
     * @param list<CredentialOption> $options The complete ordered credentials
     */
    public function __construct(array $options, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        $this->options = Check::listOf($options, CredentialOption::class, 'A deferred group start retains credentials.', 1);
        Check::input((new GroupCredentials())->problem($this->options)?->error === \SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::GroupUserMissing, 'A deferred group start retains a password without a user.');
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
    public function problem(): RefusedSetting
    {
        $problem = (new GroupCredentials())->problem($this->options);
        Check::invariant($problem !== null, 'A deferred group start retains an intrinsic credential condition.');
        return $problem;
    }

    /**
     * Writes every credential in its original order.
     */
    public function render(Output $out): void
    {
        $out->keyword('START', 'GROUP_REPLICATION')->list($this->options);
    }
}
