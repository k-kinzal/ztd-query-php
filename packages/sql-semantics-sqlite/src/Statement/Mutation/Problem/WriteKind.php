<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem;

/**
 * The ways a statement can write a column: by inserting a row or by updating one.
 *
 * Source: https://sqlite.org/gencol.html.
 *
 * @visibility SqlSemantics
 */
enum WriteKind: string
{
    case Insert = 'cannot INSERT into generated column';
    case Update = 'cannot UPDATE generated column';
}
