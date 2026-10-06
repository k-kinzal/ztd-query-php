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
 * https://www.postgresql.org/docs/17/sql-do.html, https://www.postgresql.org/docs/17/sql-notify.html.
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
    case NotBoolean = '%s requires a Boolean value';
    case NotInteger = '%s requires an integer value';
    case ParallelWithoutValue = 'parallel option requires a value between 0 and 1024';
    case ParallelRange = 'parallel workers for vacuum must be between 0 and 1024';
    case ParallelFull = 'VACUUM FULL cannot be performed in parallel';
    case BufferLimitFull = 'BUFFER_USAGE_LIMIT cannot be specified for VACUUM FULL';
    case PageSkippingFull = 'VACUUM option DISABLE_PAGE_SKIPPING cannot be used with FULL';
    case ToastFull = 'PROCESS_TOAST required with VACUUM FULL';
    case DatabaseStatsTables = 'ONLY_DATABASE_STATS cannot be specified with a list of tables';
    case DatabaseStatsOptions = 'ONLY_DATABASE_STATS cannot be specified with other VACUUM options';
    case RequiresAnalyze = 'EXPLAIN option %s requires ANALYZE';
    case GenericPlanWithAnalyze = 'EXPLAIN options ANALYZE and GENERIC_PLAN cannot be used together';
    case SystemConcurrently = 'cannot reindex system catalogs concurrently';
    case PayloadTooLong = 'payload string too long';
}
