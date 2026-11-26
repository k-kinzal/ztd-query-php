<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory;

/**
 * A native SQLite connection inventory.
 *
 * @visibility public
 * @example Selecting the database registry
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryKind::Databases->value // => 'database_list'
 */
enum InventoryKind: string
{
    case Databases = 'database_list';
    case Collations = 'collation_list';
    case CompileOptions = 'compile_options';
    case Functions = 'function_list';
    case Modules = 'module_list';
    case Pragmas = 'pragma_list';
}
