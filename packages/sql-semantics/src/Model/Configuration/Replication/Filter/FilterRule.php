<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Filter;

/**
 * The replication filter rules CHANGE REPLICATION FILTER sets.
 * @visibility public
 * @example Reading the keyword
 *     \SqlSemantics\Model\Configuration\Replication\Filter\FilterRule::WildDoTable->value // => 'REPLICATE_WILD_DO_TABLE'
 */
enum FilterRule: string
{
    case DoDatabase = 'REPLICATE_DO_DB';
    case IgnoreDatabase = 'REPLICATE_IGNORE_DB';
    case DoTable = 'REPLICATE_DO_TABLE';
    case IgnoreTable = 'REPLICATE_IGNORE_TABLE';
    case WildDoTable = 'REPLICATE_WILD_DO_TABLE';
    case WildIgnoreTable = 'REPLICATE_WILD_IGNORE_TABLE';
    case RewriteDatabase = 'REPLICATE_REWRITE_DB';
}
