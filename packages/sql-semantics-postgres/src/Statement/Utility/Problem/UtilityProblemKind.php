<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem;

/**
 * The rules of PostgreSQL a grammatical utility command can break, in the words of the server.
 *
 * A `%s` in a message stands for a name or text the problem is about.
 * Source: https://www.postgresql.org/docs/17/sql-set.html, https://www.postgresql.org/docs/17/sql-explain.html,
 * https://www.postgresql.org/docs/17/sql-vacuum.html, https://www.postgresql.org/docs/17/sql-analyze.html,
 * https://www.postgresql.org/docs/17/sql-cluster.html, https://www.postgresql.org/docs/17/sql-reindex.html,
 * https://www.postgresql.org/docs/17/sql-do.html.
 *
 * @visibility public
 * @example Reading which rule a command breaks
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SET CATALOG 'd'");
 *     $operation->facts->diagnostics[0]->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind::CatalogChange
 */
enum UtilityProblemKind: string
{
    case CatalogChange = 'current database cannot be changed';
    case ZoneIntervalFields = 'time zone interval must be HOUR or HOUR TO MINUTE';
    case UnknownOption = 'unrecognized %s option "%s"';
    case UnknownFormat = 'unrecognized value for EXPLAIN option "%s": "%s"';
    case MissingArgument = '%s requires a parameter';
    case MissingColumn = 'column "%s" of relation "%s" does not exist';
    case ColumnsWithoutAnalyze = 'ANALYZE option must be specified when a column list is provided';
    case NoInlineCode = 'no inline code specified';
    case RedundantOptions = 'conflicting or redundant options';
}
