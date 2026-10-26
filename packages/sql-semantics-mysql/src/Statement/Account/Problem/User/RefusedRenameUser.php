<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem\User;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\Renamings;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete rename list outside the ordinary account-identity domain.
 *
 * @visibility public
 * @example Describing the retained identity contradiction
 *     $account = new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser();
 *     $request = new \SqlSemantics\Platform\MySql\Statement\Account\RenameUser([new \SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming($account, new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
 *     $request->problem->message() // => 'RENAME USER requires consistent source and destination identities throughout its ordered pairs.'
 */
final class RefusedRenameUser implements Diagnostic
{
    use Snapshot;

    /**
     * @var non-empty-list<UserRenaming> The ordered pairs that establish the contradiction
     */
    public readonly array $renamings;

    /**
     * @param list<UserRenaming> $renamings The complete ordered rename list
     */
    public function __construct(array $renamings)
    {
        $this->renamings = Check::listOf($renamings, UserRenaming::class, 'An account rename condition describes at least one pair.', 1);
        Check::input((new Renamings())->problem($this->renamings) !== null, 'The account rename condition requires contradictory identity requirements.');
    }

    /**
     * Describes a fixed identity contradiction without selecting catalog-dependent errors.
     */
    public function message(): string
    {
        $problem = (new Renamings())->problem($this->renamings);
        Check::invariant($problem !== null, 'An account rename condition retains contradictory identity requirements.');
        return $problem;
    }
}
