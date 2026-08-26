<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

/**
 * Reports data that cannot describe anything a database would accept.
 *
 * The definitions ZTD works from — a dialect's lexical data, a table's
 * partitioning, the projection a rewritten statement will read back — are
 * built at runtime from a schema, a parsed statement, or a platform package,
 * not written out by hand at the call site. Data that could not describe
 * anything is therefore something ZTD refuses, in the same way it refuses a
 * statement it cannot simulate, rather than an assertion about the code that
 * passed it: a caller can catch this and say which definition was at fault.
 *
 * Refusing at the point the definition is built is what keeps the failure
 * near its cause. An empty delimiter carried into the scanner would loop
 * forever, and an empty predicate carried into a partition would silently
 * match every row.
 */
final class InvalidDefinitionException extends SimulationException
{
}
