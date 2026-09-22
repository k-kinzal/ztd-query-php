<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

/**

 * @visibility public

  * @example Inspecting DistinctRows
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
 *     $sql = 'WITH q AS (SELECT id FROM t) SELECT DISTINCT q.id, missing FROM q WHERE q.id>0 GROUP BY q.id HAVING q.id>0 ORDER BY q.id DESC LIMIT 2 OFFSET 1';
 *     $statement = $binder->bind($sql, strict: false);
 *     $statement->quantifier instanceof \SqlSemantics\Model\Query\DistinctRows // => true
 */
final class DistinctRows implements Quantifier
{
}
