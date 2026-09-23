<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

/**
 * A session principal or public fallback mapping, retained without resolving a username.
 * @visibility public
 * @example Inspecting the current user selection
 *     \SqlSemantics\Model\Definition\Foreign\MappingPrincipal::CurrentUser->value // => 'CURRENT_USER'
 */
enum MappingPrincipal: string
{
    case CurrentUser = 'CURRENT_USER';
    case CurrentRole = 'CURRENT_ROLE';
    case SessionUser = 'SESSION_USER';
    case PublicDefault = 'PUBLIC';
}
