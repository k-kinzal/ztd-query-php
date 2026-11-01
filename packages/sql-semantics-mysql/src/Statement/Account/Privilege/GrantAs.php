<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * `AS user [WITH ROLE …]` of GRANT (8.0.16+): the account and active roles whose privileges and partial revokes the grant is executed with.
 *
 * Mirrors LEX_GRANT_AS.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-as.
 *
 * @visibility public
 * @example Reading the account a grant is executed as
 *     $as = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('GRANT SELECT ON *.* TO u AS admin WITH ROLE DEFAULT')->as;
 *     $as?->roles?->set // => \SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet::Default
 */
final class GrantAs implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Account $user The account
     * @param RoleSelection|null $roles The roles of WITH ROLE, when written
     */
    public function __construct(public readonly Account $user, public readonly ?RoleSelection $roles = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('AS')->node($this->user);
        if ($this->roles !== null) {
            $out->keyword('WITH', 'ROLE')->node($this->roles);
        }
    }
}
