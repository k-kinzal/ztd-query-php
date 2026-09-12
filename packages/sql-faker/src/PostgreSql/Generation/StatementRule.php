<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation;

/**
 * Statement start rules available to PostgreSQL SQL generation.
 *
 * Values correspond to grammar rule names in PostgreSQL's gram.y.
 *
 * @visibility public
 * @example Enumerate the supported statement choices
 *     array_column(\SqlFaker\PostgreSql\Generation\StatementRule::cases(), 'name') // => ['Select', 'Insert', 'Update', 'Delete', 'CreateTable', 'CreateTableAs', 'CreateDomain', 'AlterTable', 'DropTable', 'SimpleStatement']
 * @example Use the existing StatementType alias
 *     \SqlFaker\PostgreSql\StatementType::Select === \SqlFaker\PostgreSql\Generation\StatementRule::Select // => true
 */
enum StatementRule: string
{
    /**
     * @visibility public
     * @example Choose the Select grammar rule
     *     \SqlFaker\PostgreSql\Generation\StatementRule::Select->value // => 'SelectStmt'
     */
    case Select = 'SelectStmt';
    /**
     * @visibility public
     * @example Choose the Insert grammar rule
     *     \SqlFaker\PostgreSql\Generation\StatementRule::Insert->value // => 'InsertStmt'
     */
    case Insert = 'InsertStmt';
    /**
     * @visibility public
     * @example Choose the Update grammar rule
     *     \SqlFaker\PostgreSql\Generation\StatementRule::Update->value // => 'UpdateStmt'
     */
    case Update = 'UpdateStmt';
    /**
     * @visibility public
     * @example Choose the Delete grammar rule
     *     \SqlFaker\PostgreSql\Generation\StatementRule::Delete->value // => 'DeleteStmt'
     */
    case Delete = 'DeleteStmt';
    /**
     * @visibility public
     * @example Choose the CreateTable grammar rule
     *     \SqlFaker\PostgreSql\Generation\StatementRule::CreateTable->value // => 'CreateStmt'
     */
    case CreateTable = 'CreateStmt';
    /**
     * @visibility public
     * @example Choose the CreateTableAs grammar rule
     *     \SqlFaker\PostgreSql\Generation\StatementRule::CreateTableAs->value // => 'CreateAsStmt'
     */
    case CreateTableAs = 'CreateAsStmt';
    /**
     * @visibility public
     * @example Choose the CreateDomain grammar rule
     *     \SqlFaker\PostgreSql\Generation\StatementRule::CreateDomain->value // => 'CreateDomainStmt'
     */
    case CreateDomain = 'CreateDomainStmt';
    /**
     * @visibility public
     * @example Choose the AlterTable grammar rule
     *     \SqlFaker\PostgreSql\Generation\StatementRule::AlterTable->value // => 'AlterTableStmt'
     */
    case AlterTable = 'AlterTableStmt';
    /**
     * @visibility public
     * @example Choose the DropTable grammar rule
     *     \SqlFaker\PostgreSql\Generation\StatementRule::DropTable->value // => 'DropStmt'
     */
    case DropTable = 'DropStmt';
    /**
     * @visibility public
     * @example Choose the SimpleStatement grammar rule
     *     \SqlFaker\PostgreSql\Generation\StatementRule::SimpleStatement->value // => 'stmt'
     */
    case SimpleStatement = 'stmt';
}
