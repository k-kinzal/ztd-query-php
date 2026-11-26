<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract\Index;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;

/**
 * Stock relations in the SQL information schema of the pinned releases.
 *
 * @visibility SqlSemantics
 */
final class InformationSchemaNames
{
    private const INFORMATION = [
        '_pg_foreign_data_wrappers' => true,
        '_pg_foreign_servers' => true,
        '_pg_foreign_table_columns' => true,
        '_pg_foreign_tables' => true,
        '_pg_user_mappings' => true,
        'administrable_role_authorizations' => true,
        'applicable_roles' => true,
        'attributes' => true,
        'character_sets' => true,
        'check_constraint_routine_usage' => true,
        'check_constraints' => true,
        'collation_character_set_applicability' => true,
        'collations' => true,
        'column_column_usage' => true,
        'column_domain_usage' => true,
        'column_options' => true,
        'column_privileges' => true,
        'column_udt_usage' => true,
        'columns' => true,
        'constraint_column_usage' => true,
        'constraint_table_usage' => true,
        'data_type_privileges' => true,
        'domain_constraints' => true,
        'domain_udt_usage' => true,
        'domains' => true,
        'element_types' => true,
        'enabled_roles' => true,
        'foreign_data_wrapper_options' => true,
        'foreign_data_wrappers' => true,
        'foreign_server_options' => true,
        'foreign_servers' => true,
        'foreign_table_options' => true,
        'foreign_tables' => true,
        'information_schema_catalog_name' => true,
        'key_column_usage' => true,
        'parameters' => true,
        'referential_constraints' => true,
        'role_column_grants' => true,
        'role_routine_grants' => true,
        'role_table_grants' => true,
        'role_udt_grants' => true,
        'role_usage_grants' => true,
        'routine_column_usage' => true,
        'routine_privileges' => true,
        'routine_routine_usage' => true,
        'routine_sequence_usage' => true,
        'routine_table_usage' => true,
        'routines' => true,
        'schemata' => true,
        'sequences' => true,
        'sql_features' => true,
        'sql_implementation_info' => true,
        'sql_parts' => true,
        'sql_sizing' => true,
        'table_constraints' => true,
        'table_privileges' => true,
        'tables' => true,
        'transforms' => true,
        'triggered_update_columns' => true,
        'triggers' => true,
        'udt_privileges' => true,
        'usage_privileges' => true,
        'user_defined_types' => true,
        'user_mapping_options' => true,
        'user_mappings' => true,
        'view_column_usage' => true,
        'view_routine_usage' => true,
        'view_table_usage' => true,
        'views' => true,
    ];

    /**
     * Tests whether the exact name is a stock information schema relation.
     */
    public static function contains(Name $name): bool
    {
        return isset(self::INFORMATION[$name->value]);
    }
}
