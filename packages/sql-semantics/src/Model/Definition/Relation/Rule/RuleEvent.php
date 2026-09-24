<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Rule;

/**
 * The command on a table that a rewrite rule rewrites.
 * @visibility public
 * @example Reading the event of a rule
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE keep AS ON DELETE TO t DO INSTEAD NOTHING');
 *     $statement->event // => \SqlSemantics\Model\Definition\Relation\Rule\RuleEvent::Delete
 */
enum RuleEvent: string
{
    case Select = 'SELECT';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
