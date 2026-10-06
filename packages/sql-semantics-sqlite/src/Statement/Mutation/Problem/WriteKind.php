<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem;

/**
 * The ways a statement can write a column: by inserting a row or by updating one.
 *
 * Source: https://sqlite.org/gencol.html.
 *
 * @visibility public
 * @example Reading how a statement writes a generated column
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a, b AS (a + 1))');
 *     $semantics->analyze('INSERT INTO t (b) VALUES (1)', [$table])->facts->diagnostics[0]->write // => \SqlSemantics\Platform\Sqlite\Statement\Mutation\Problem\WriteKind::Insert
 */
enum WriteKind: string
{
    case Insert = 'cannot INSERT into generated column';
    case Update = 'cannot UPDATE generated column';
}
