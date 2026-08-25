<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

/**
 * Statement start rules available to MySQL SQL generation.
 */
enum StatementRule: string
{
    case Select = 'select_stmt';
    case Insert = 'insert_stmt';
    case Update = 'update_stmt';
    case Delete = 'delete_stmt';
    case CreateTable = 'create_table_stmt';
    case AlterTable = 'alter_table_stmt';
    case DropTable = 'drop_table_stmt';
    case SimpleStatement = 'simple_statement';
}
