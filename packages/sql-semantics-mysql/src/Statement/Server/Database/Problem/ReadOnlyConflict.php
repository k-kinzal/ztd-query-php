<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Opposite READ ONLY values occur in one database option list.
 *
 * DEFAULT denotes zero; repeated equal values are permitted. The conflict
 * stops input processing at the second distinct value, before later options.
 *
 * @visibility public
 * @example Describing the conflicting values
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\ReadOnlyConflict())->message() // => "Conflicting declarations: 'READ ONLY=0' and 'READ ONLY=1'"
 */
final class ReadOnlyConflict implements Diagnostic
{
    use Snapshot;

    /**
     * Describes the conflict in its fixed value order.
     */
    public function message(): string
    {
        return "Conflicting declarations: 'READ ONLY=0' and 'READ ONLY=1'";
    }
}
