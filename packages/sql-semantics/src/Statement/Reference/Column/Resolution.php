<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Column;

/**
 * The outcome of resolving a value reference: a column, output alias, variable or parameter binding, or a missing, ambiguous or conditional name.
 *
 * Finding a candidate and resolving uniquely are different outcomes.
 *
 * @visibility public
 * @example Reading the resolution of a column use
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $semantics->analyze('SELECT a FROM t', [$table])->field('a')->resolution instanceof \SqlSemantics\Statement\Reference\Column\ResolvedColumn // => true
 */
interface Resolution
{
}
