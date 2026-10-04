<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication;

/**
 * The words a replication statement is written with: MASTER and SLAVE, or SOURCE, REPLICA and BINARY LOGS AND GTIDS.
 *
 * A release accepts the legacy words only before it introduced the current
 * ones, both while the legacy words are deprecated, and the current ones only
 * after it removed the legacy words. Where the server treats the two
 * spellings as one request (the manual calls the current statement an alias
 * of the deprecated one, and the parser fills the same fields and only adds a
 * deprecation warning), the vocabulary is fixed by the release: CHANGE
 * MASTER and its MASTER_ options, RESET SLAVE and RESET MASTER are legacy in
 * MySQL 5.6 and 5.7 and read as their current synonyms from 8.0 (RESET
 * MASTER up to 8.1 has no current spelling and stays legacy). START SLAVE
 * and STOP SLAVE keep the spelling written: the keyword SLAVE of their
 * grammar rule is shared with SHOW SLAVE STATUS, whose result columns are
 * named after the spelling.
 * Source: https://dev.mysql.com/doc/refman/8.0/en/change-master-to.html,
 * https://dev.mysql.com/doc/refman/8.0/en/start-replica.html,
 * https://dev.mysql.com/doc/relnotes/mysql/8.2/en/news-8-2-0.html.
 *
 * @visibility public
 * @example Reading the vocabulary of a statement
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('STOP SLAVE')->statement->terminology // => \SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy
 */
enum Terminology
{
    case Legacy;
    case Current;
}
