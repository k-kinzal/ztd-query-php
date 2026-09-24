<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Policy;

/**
 * How a row security policy combines with the other policies of its table.
 * @visibility public
 * @example Reading a restrictive policy
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE docs(owner TEXT)')))->bind('CREATE POLICY own ON docs AS RESTRICTIVE USING (owner = CURRENT_USER)');
 *     $statement->mode // => \SqlSemantics\Model\Definition\Relation\Policy\PolicyMode::Restrictive
 */
enum PolicyMode: string
{
    case Permissive = 'PERMISSIVE';
    case Restrictive = 'RESTRICTIVE';
}
