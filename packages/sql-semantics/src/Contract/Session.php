<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

/**
 * The state of one database session that changes what a statement resolves to: the session variables a platform reads while resolving.
 *
 * Each platform defines what its session holds. A context without a session resolves as a new
 * session of the server with its default settings would.
 *
 * @visibility public
 * @example Giving a MySQL context the collation of its connection
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $session = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('latin1_swedish_ci'));
 *     $semantics->context([], true, null, $session)->session === $session // => true
 */
interface Session
{
}
