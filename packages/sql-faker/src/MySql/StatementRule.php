<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

/**
 * Statement start rules available to MySQL SQL generation.
 *
 * @visibility public
 * @example Enumerate the supported statement choices
 *     array_column(\SqlFaker\MySql\StatementRule::cases(), 'name') // => ['Select', 'Insert', 'Update', 'Delete', 'CreateTable', 'AlterTable', 'DropTable', 'SimpleStatement']
 * @example Use the existing StatementType alias
 *     \SqlFaker\MySql\StatementType::Select === \SqlFaker\MySql\StatementRule::Select // => true
 */
enum StatementRule: string
{
    /**
     * @visibility public
     * @example Choose the Select grammar rule
     *     \SqlFaker\MySql\StatementRule::Select->value // => 'select_stmt'
     */
    case Select = 'select_stmt';
    /**
     * @visibility public
     * @example Choose the Insert grammar rule
     *     \SqlFaker\MySql\StatementRule::Insert->value // => 'insert_stmt'
     */
    case Insert = 'insert_stmt';
    /**
     * @visibility public
     * @example Choose the Update grammar rule
     *     \SqlFaker\MySql\StatementRule::Update->value // => 'update_stmt'
     */
    case Update = 'update_stmt';
    /**
     * @visibility public
     * @example Choose the Delete grammar rule
     *     \SqlFaker\MySql\StatementRule::Delete->value // => 'delete_stmt'
     */
    case Delete = 'delete_stmt';
    /**
     * @visibility public
     * @example Choose the CreateTable grammar rule
     *     \SqlFaker\MySql\StatementRule::CreateTable->value // => 'create_table_stmt'
     */
    case CreateTable = 'create_table_stmt';
    /**
     * @visibility public
     * @example Choose the AlterTable grammar rule
     *     \SqlFaker\MySql\StatementRule::AlterTable->value // => 'alter_table_stmt'
     */
    case AlterTable = 'alter_table_stmt';
    /**
     * @visibility public
     * @example Choose the DropTable grammar rule
     *     \SqlFaker\MySql\StatementRule::DropTable->value // => 'drop_table_stmt'
     */
    case DropTable = 'drop_table_stmt';
    /**
     * @visibility public
     * @example Choose the SimpleStatement grammar rule
     *     \SqlFaker\MySql\StatementRule::SimpleStatement->value // => 'simple_statement'
     */
    case SimpleStatement = 'simple_statement';
}
