<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

/**
 * Available statement types for MySQL SQL generation.
 */
enum StatementType: string
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
