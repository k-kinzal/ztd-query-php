<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership;

/**
 * A native relation eligible for an automatic sequence dependency.
 *
 * @visibility public
 * @example Selecting a view as the referenced relation
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwnerKind::View->value // => 'view'
 */
enum SequenceOwnerKind: string
{
    case BaseTable = 'table';
    case ForeignTable = 'foreign';
    case View = 'view';
    case PartitionedTable = 'partitioned';
}
