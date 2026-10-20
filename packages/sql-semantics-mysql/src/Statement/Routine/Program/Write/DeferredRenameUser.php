<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\Renamings;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored account rename list retaining contradictory identity requirements.
 *
 * The complete ordered pairs remain available to the account consumer, including
 * partial legacy effects and current-account references. An unreached branch
 * does not apply them. Ordinary RenameUser values exclude this contradiction.
 *
 * @visibility public
 * @example Retaining a contradictory pair until invocation
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() RENAME USER a TO a');
 *     $operation->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRenameUser // => true
 */
final class DeferredRenameUser implements ProgramStatement
{
    use Snapshot;

    /**
     * @var non-empty-list<UserRenaming> The complete renamings in written order
     */
    public readonly array $renamings;

    /**
     * @param list<UserRenaming> $renamings The complete ordered pairs
     */
    public function __construct(array $renamings)
    {
        $this->renamings = Check::listOf($renamings, UserRenaming::class, 'RENAME USER names at least one pair.', 1);
        Check::input((new Renamings())->problem($this->renamings) !== null, 'A deferred account rename retains contradictory identity requirements.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Leaves account catalog resolution to the reached instruction's consumer.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
    }

    /**
     * Describes the complete retained request without an original SQL string.
     */
    public function problem(): \SqlSemantics\Platform\MySql\Statement\Account\Problem\User\RefusedRenameUser
    {
        return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\User\RefusedRenameUser($this);
    }

    /**
     * Writes every retained pair in its original semantic role.
     */
    public function render(Output $out): void
    {
        $out->keyword('RENAME', 'USER')->list($this->renamings);
    }
}
