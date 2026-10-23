<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Source;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A warning caused by an input spelling that the semantic value does not retain.
 *
 * The subject belongs to the statement by identity. Its decoded value is independent of this
 * notice: quoting the same identifier can remove the warning without changing name lookup.
 * The offset is the input byte boundary at which the construct has been read.
 *
 * @visibility public
 * @example An identifier spelling that MySQL deprecates
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT 1 AS full');
 *     $query->sources->notices[0]->offset // => 16
 */
final class SourceNotice
{
    use Snapshot;

    /**
     * @param Node|Name $subject The semantic occurrence read from the input
     * @param Warning $warning The spelling-dependent warning
     * @param int $offset The byte boundary after the spelling
     */
    public function __construct(public readonly Node|Name $subject, public readonly Warning $warning, public readonly int $offset)
    {
        Check::input($offset >= 0, 'An input notice has a non-negative byte boundary.');
    }
}
