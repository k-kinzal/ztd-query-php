<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Filter;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * The replication filters CHANGE REPLICATION FILTER sets: the server's OPT_REPLICATE_* options.
 *
 * Each case holds the keyword it is written with. The database filters list
 * database names, the table filters database-qualified table names, the
 * wildcard filters `db.table` patterns and REPLICATE_REWRITE_DB pairs of
 * database names.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-filter.html.
 *
 * @visibility public
 * @example Reading the keyword of a filter
 *     \SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind::RewriteDb->value // => 'REPLICATE_REWRITE_DB'
 */
enum FilterKind: string
{
    case DoDb = 'REPLICATE_DO_DB';
    case IgnoreDb = 'REPLICATE_IGNORE_DB';
    case DoTable = 'REPLICATE_DO_TABLE';
    case IgnoreTable = 'REPLICATE_IGNORE_TABLE';
    case WildDoTable = 'REPLICATE_WILD_DO_TABLE';
    case WildIgnoreTable = 'REPLICATE_WILD_IGNORE_TABLE';
    case RewriteDb = 'REPLICATE_REWRITE_DB';

    /**
     * Answers the class of the values the filter lists.
     *
     * @return class-string<Name|QualifiedName|Text|DatabaseRewrite>
     */
    public function member(): string
    {
        return match ($this) {
            self::DoDb, self::IgnoreDb => Name::class,
            self::DoTable, self::IgnoreTable => QualifiedName::class,
            self::WildDoTable, self::WildIgnoreTable => Text::class,
            self::RewriteDb => DatabaseRewrite::class,
        };
    }
}
