<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Identifies one user-to-server mapping through a role selector and required foreign server.
 * @visibility public
 * @example Keeping a principal symbolic
 *     $target = new \SqlSemantics\Model\Definition\Foreign\UserMappingIdentity(\SqlSemantics\Model\Definition\Foreign\MappingPrincipal::CurrentUser, 'remote');
 *     $target->server // => 'remote'
 */
final class UserMappingIdentity
{
    /**
     * The current user and public mapping remain distinct from named roles.
     * @throws InvalidStructure
     */
    public function __construct(public readonly NamedRole|MappingPrincipal $user, public readonly string $server)
    {
        if ($server === '') {
            throw new InvalidStructure('A user mapping requires its foreign server name.');
        }
    }
}
