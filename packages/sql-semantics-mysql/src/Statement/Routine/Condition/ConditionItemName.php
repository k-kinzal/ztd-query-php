<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

/**
 * The condition information items SIGNAL and RESIGNAL set and GET DIAGNOSTICS reads.
 *
 * Each case holds its keyword. RETURNED_SQLSTATE is read only: SIGNAL sets
 * it through the condition value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/diagnostics-area.html#diagnostics-area-information-items.
 *
 * @visibility public
 * @example Reading the keyword of an item
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::MessageText->value // => 'MESSAGE_TEXT'
 */
enum ConditionItemName: string
{
    case ClassOrigin = 'CLASS_ORIGIN';
    case SubclassOrigin = 'SUBCLASS_ORIGIN';
    case ConstraintCatalog = 'CONSTRAINT_CATALOG';
    case ConstraintSchema = 'CONSTRAINT_SCHEMA';
    case ConstraintName = 'CONSTRAINT_NAME';
    case CatalogName = 'CATALOG_NAME';
    case SchemaName = 'SCHEMA_NAME';
    case TableName = 'TABLE_NAME';
    case ColumnName = 'COLUMN_NAME';
    case CursorName = 'CURSOR_NAME';
    case MessageText = 'MESSAGE_TEXT';
    case MysqlErrno = 'MYSQL_ERRNO';
    case ReturnedSqlstate = 'RETURNED_SQLSTATE';
}
