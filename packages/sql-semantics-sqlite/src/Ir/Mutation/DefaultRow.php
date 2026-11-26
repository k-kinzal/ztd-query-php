<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * One INSERT row formed from the target's declared defaults.
 *
 * @visibility public
 * @example Selecting default row input for a resolved table
 *     $target = new \SqlSemantics\Platform\Sqlite\Ir\TableSource(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('main'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('items'), []);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Mutation\Insert($target, [], new \SqlSemantics\Platform\Sqlite\Ir\Mutation\DefaultRow()))->toString() // => 'INSERT INTO "main"."items" DEFAULT VALUES'
 */
final class DefaultRow
{
    use Snapshot;
}
