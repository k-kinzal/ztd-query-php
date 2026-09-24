<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Policy;

/**
 * The commands a row security policy applies to.
 * @visibility public
 * @example Reading the command of a policy
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE docs(owner TEXT)')))->bind('CREATE POLICY own ON docs FOR INSERT WITH CHECK (owner = CURRENT_USER)');
 *     $statement->command // => \SqlSemantics\Model\Definition\Relation\Policy\PolicyCommand::Insert
 */
enum PolicyCommand: string
{
    case All = 'ALL';
    case Select = 'SELECT';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
