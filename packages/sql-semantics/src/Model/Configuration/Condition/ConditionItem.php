<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Condition;

/**
 * A condition information item of the diagnostics area, named by its SQL keyword.
 * @visibility public
 * @example Checking which items SIGNAL may set
 *     [\SqlSemantics\Model\Configuration\Condition\ConditionItem::MessageText->signalable(), \SqlSemantics\Model\Configuration\Condition\ConditionItem::ReturnedSqlState->signalable()] // => [true, false]
 */
enum ConditionItem: string
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
    case MySqlErrorNumber = 'MYSQL_ERRNO';
    case ReturnedSqlState = 'RETURNED_SQLSTATE';

    /**
     * Whether SIGNAL and RESIGNAL may assign the item; the SQLSTATE comes from the signaled condition instead.
     */
    public function signalable(): bool
    {
        return $this !== self::ReturnedSqlState;
    }
}
