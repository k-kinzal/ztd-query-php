<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

/**
 * Statement start rules available to PostgreSQL SQL generation.
 *
 * Values correspond to grammar rule names in PostgreSQL's gram.y.
 */
enum StatementRule: string
{
    case Select = 'SelectStmt';
    case Insert = 'InsertStmt';
    case Update = 'UpdateStmt';
    case Delete = 'DeleteStmt';
    case CreateTable = 'CreateStmt';
    case CreateTableAs = 'CreateAsStmt';
    case CreateDomain = 'CreateDomainStmt';
    case AlterTable = 'AlterTableStmt';
    case DropTable = 'DropStmt';
    case SimpleStatement = 'stmt';
}
