<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

/**
 * Available statement types for SQLite SQL generation.
 *
 * Values correspond to grammar rule names in SQLite's parse.y.
 * The 'cmd' rule is the main statement entry point containing all SQL commands.
 */
enum StatementType: string
{
    case Select = 'select';
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
    case CreateTable = 'create_table';
    case AlterTable = 'alter_table';
    case DropTable = 'drop_table';
    case SimpleStatement = 'cmd';
}
