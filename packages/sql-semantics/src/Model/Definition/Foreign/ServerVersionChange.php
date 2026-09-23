<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

/**
 * Selects whether an existing server version remains unchanged or becomes absent.
 * @visibility public
 * @example Inspecting an explicit version removal
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SERVER remote VERSION NULL');
 *     $statement->version === \SqlSemantics\Model\Definition\Foreign\ServerVersionChange::Remove // => true
 */
enum ServerVersionChange
{
    case Keep;
    case Remove;
}
