<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * An account receiving a grant, with the optional authentication of legacy GRANT.
 *
 * GRANT cannot hold ALTER USER password management, additional factors or
 * USER(). Keeping this operand separate makes those fields unavailable in
 * a grant structure. An existing UserSpecification can be converted only
 * when it satisfies the same invariant.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/grant.html.
 *
 * @visibility public
 * @example Holding an account without authentication
 *     $grantee = new \SqlSemantics\Platform\MySql\Statement\Account\User\Grantee(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('app')));
 *     $grantee->identification // => null
 */
final class Grantee implements Node
{
    use Snapshot;

    /**
     * @param Account $user The named account or CURRENT_USER
     * @param Identification|null $identification The optional legacy authentication
     */
    public function __construct(public readonly Account $user, public readonly ?Identification $identification = null)
    {
        Check::input($identification?->credential !== Credential::RandomPassword, 'GRANT authentication does not generate a random password.');
    }

    /**
     * Converts an account specification without retaining its unrelated fields.
     */
    public static function from(Node $specification): self
    {
        if ($specification instanceof self) {
            return $specification;
        }
        Check::input($specification instanceof UserSpecification && $specification->user instanceof Account && $specification->granted(), 'A GRANT account is a named account with at most one authentication method.');

        return new self($specification->user, $specification->identification);
    }

    /**
     * Writes the account and its optional authentication.
     */
    public function render(Output $out): void
    {
        $out->node($this->user)->node($this->identification);
    }
}
