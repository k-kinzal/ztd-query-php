<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Trigger;

/**
 * Whether a relation trigger fires once per affected row or once per statement.
 * @visibility public
 * @example Reading the firing granularity of a trigger
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE TRIGGER audit AFTER INSERT ON t FOR EACH ROW EXECUTE FUNCTION log_row()');
 *     $statement->level // => \SqlSemantics\Model\Definition\Trigger\TriggerLevel::Row
 */
enum TriggerLevel: string
{
    case Row = 'ROW';
    case Statement = 'STATEMENT';
}
