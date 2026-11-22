<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

/**
 * How the options of a utility command are written.
 *
 * The current syntax writes any options in a parenthesized list, each with
 * an optional value. The older syntax writes a few options as bare words in
 * a fixed order directly after the command. Both name the same options.
 * Source: https://www.postgresql.org/docs/17/sql-vacuum.html, https://www.postgresql.org/docs/17/sql-explain.html.
 *
 * @visibility SqlSemantics
 */
enum OptionSyntax
{
    case Words;
    case Parenthesized;
}
