<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract\Index;

/**
 * The declared physical ownership of an index relation.
 *
 * @visibility SqlSemantics
 */
enum IndexDeclarationKind
{
    case Ordinary;
    case KeyConstraint;
    case ExclusionConstraint;
    case Partitioned;
    case PartitionBranch;
    case PartitionLeaf;
}
