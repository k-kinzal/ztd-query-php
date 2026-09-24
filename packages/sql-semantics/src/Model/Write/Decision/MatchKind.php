<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Decision;

/**
 * Closed MatchKind alternatives.
 * @visibility public
 * @example Reading a merge decision's match category
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN NOT MATCHED BY SOURCE THEN DELETE');
 *     $statement->merge->actions[0]->match // => \SqlSemantics\Model\Write\Decision\MatchKind::MissingSource
 */
enum MatchKind: string
{
    case Matched = 'matched';
    case MissingSource = 'not-matched-by-source';
    case MissingTarget = 'not-matched-by-target';
}
