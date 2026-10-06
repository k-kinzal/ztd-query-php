<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence;

/**
 * The sequence options without an operand.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html, https://www.postgresql.org/docs/17/sql-altersequence.html.
 *
 * @visibility public
 * @example Reading the spelling of a flag
 *     \SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceFlagKind::NoMaxValue->value // => 'NO MAXVALUE'
 */
enum SequenceFlagKind: string
{
    case Cycle = 'CYCLE';
    case NoCycle = 'NO CYCLE';
    case NoMinValue = 'NO MINVALUE';
    case NoMaxValue = 'NO MAXVALUE';
    case Restart = 'RESTART';
    case Logged = 'LOGGED';
    case Unlogged = 'UNLOGGED';
}
