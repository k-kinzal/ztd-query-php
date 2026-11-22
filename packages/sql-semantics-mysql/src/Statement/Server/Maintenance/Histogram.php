<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Node;

/**
 * The histogram request of ANALYZE TABLE (MySQL 8.0 and later): update, load or drop the histograms of columns.
 *
 * Mirrors Sql_cmd_analyze_table::Histogram_command.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 *
 * @visibility SqlSemantics
 */
interface Histogram extends Node
{
    /**
     * Answers the column names in written order.
     *
     * @return list<Name>
     */
    public function columns(): array;
}
