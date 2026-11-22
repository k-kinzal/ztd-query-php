<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Type;

use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The type of an expression that can only be SQL NULL and has no other type yet.
 *
 * @visibility SqlSemantics
 */
final class NullOnly implements TypeFact
{
    use Snapshot;
}
