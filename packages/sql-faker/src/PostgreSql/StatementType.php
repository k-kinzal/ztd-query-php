<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

/**
 * Available statement types for PostgreSQL SQL generation.
 *
 * Values correspond to grammar rule names in PostgreSQL's gram.y.
 */
enum StatementType: string
{
    case Select = 'SelectStmt';
    case Insert = 'InsertStmt';
    case Update = 'UpdateStmt';
    case Delete = 'DeleteStmt';
    case CreateTable = 'CreateStmt';
    case AlterTable = 'AlterTableStmt';
    case DropTable = 'DropStmt';
    case SimpleStatement = 'stmt';
}
