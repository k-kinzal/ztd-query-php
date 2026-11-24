<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

/**
 * A session-supplied authorization identity for a schema declaration.
 *
 * @visibility public
 * @example Selecting the authorization role established by the session
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole::Session->value // => 'SESSION_USER'
 */
enum SchemaSessionRole: string
{
    case Effective = 'CURRENT_USER';
    case Session = 'SESSION_USER';
}
