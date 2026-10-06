<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence;

/**
 * The sequence options that take a number.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html, https://www.postgresql.org/docs/17/sql-altersequence.html.
 *
 * @visibility public
 * @example Reading the keyword of a numeric option
 *     \SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceNumberKind::Increment->value // => 'INCREMENT'
 */
enum SequenceNumberKind: string
{
    case Cache = 'CACHE';
    case Increment = 'INCREMENT';
    case MaxValue = 'MAXVALUE';
    case MinValue = 'MINVALUE';
    case Start = 'START';
    case Restart = 'RESTART';
}
