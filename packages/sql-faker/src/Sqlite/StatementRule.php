<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

/**
 * Statement start rules available to SQLite SQL generation.
 *
 * Values correspond to grammar rule names in SQLite's parse.y.
 * The 'cmd' rule is the main statement entry point containing all SQL commands.
 *
 * @visibility public
 * @example Enumerate the supported statement choices
 *     array_column(\SqlFaker\Sqlite\StatementRule::cases(), 'name') // => ['Select', 'Insert', 'Update', 'Delete', 'CreateTable', 'AlterTable', 'DropTable', 'SimpleStatement']
 * @example Use the existing StatementType alias
 *     \SqlFaker\Sqlite\StatementType::Select === \SqlFaker\Sqlite\StatementRule::Select // => true
 */
enum StatementRule: string
{
    /**
     * @visibility public
     * @example Choose the Select grammar rule
     *     \SqlFaker\Sqlite\StatementRule::Select->value // => 'select'
     */
    case Select = 'select';
    /**
     * @visibility public
     * @example Choose the Insert grammar rule
     *     \SqlFaker\Sqlite\StatementRule::Insert->value // => 'insert'
     */
    case Insert = 'insert';
    /**
     * @visibility public
     * @example Choose the Update grammar rule
     *     \SqlFaker\Sqlite\StatementRule::Update->value // => 'update'
     */
    case Update = 'update';
    /**
     * @visibility public
     * @example Choose the Delete grammar rule
     *     \SqlFaker\Sqlite\StatementRule::Delete->value // => 'delete'
     */
    case Delete = 'delete';
    /**
     * @visibility public
     * @example Choose the CreateTable grammar rule
     *     \SqlFaker\Sqlite\StatementRule::CreateTable->value // => 'create_table'
     */
    case CreateTable = 'create_table';
    /**
     * @visibility public
     * @example Choose the AlterTable grammar rule
     *     \SqlFaker\Sqlite\StatementRule::AlterTable->value // => 'alter_table'
     */
    case AlterTable = 'alter_table';
    /**
     * @visibility public
     * @example Choose the DropTable grammar rule
     *     \SqlFaker\Sqlite\StatementRule::DropTable->value // => 'drop_table'
     */
    case DropTable = 'drop_table';
    /**
     * @visibility public
     * @example Choose the SimpleStatement grammar rule
     *     \SqlFaker\Sqlite\StatementRule::SimpleStatement->value // => 'cmd'
     */
    case SimpleStatement = 'cmd';
}
