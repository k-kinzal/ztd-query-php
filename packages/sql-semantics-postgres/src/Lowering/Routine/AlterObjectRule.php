<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\ChangeOwner;
use SqlSemantics\Platform\PostgreSql\Statement\Object\ExtensionDependency;
use SqlSemantics\Platform\PostgreSql\Statement\Object\SetSchema;

/**
 * Lowers ALTER ... SET SCHEMA, OWNER TO and DEPENDS ON EXTENSION.
 *
 * Rule: PG-ALTER-OBJECT-LOWER-001. Scope: `AlterObjectSchemaStmt`,
 * `AlterOwnerStmt`, `AlterObjectDependsStmt`, `opt_no`. Constructors:
 * `SetSchema`, `ChangeOwner`, `ExtensionDependency`; the object part is read
 * by PG-OBJECT-LOWER-001. Source: https://www.postgresql.org/docs/17/sql-alterfunction.html,
 * https://www.postgresql.org/docs/17/sql-altertable.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterObjectRule
{
    /**
     * The kind each SET SCHEMA production moves.
     */
    private const SCHEMAS = [
        'AlterObjectSchemaStmt: ALTER AGGREGATE aggregate_with_argtypes SET SCHEMA name' => 'AGGREGATE',
        'AlterObjectSchemaStmt: ALTER COLLATION any_name SET SCHEMA name' => 'COLLATION',
        'AlterObjectSchemaStmt: ALTER CONVERSION_P any_name SET SCHEMA name' => 'CONVERSION',
        'AlterObjectSchemaStmt: ALTER DOMAIN_P any_name SET SCHEMA name' => 'DOMAIN',
        'AlterObjectSchemaStmt: ALTER EXTENSION name SET SCHEMA name' => 'EXTENSION',
        'AlterObjectSchemaStmt: ALTER FUNCTION function_with_argtypes SET SCHEMA name' => 'FUNCTION',
        'AlterObjectSchemaStmt: ALTER OPERATOR operator_with_argtypes SET SCHEMA name' => 'OPERATOR',
        'AlterObjectSchemaStmt: ALTER OPERATOR CLASS any_name USING name SET SCHEMA name' => 'OPERATOR CLASS',
        'AlterObjectSchemaStmt: ALTER OPERATOR FAMILY any_name USING name SET SCHEMA name' => 'OPERATOR FAMILY',
        'AlterObjectSchemaStmt: ALTER PROCEDURE function_with_argtypes SET SCHEMA name' => 'PROCEDURE',
        'AlterObjectSchemaStmt: ALTER ROUTINE function_with_argtypes SET SCHEMA name' => 'ROUTINE',
        'AlterObjectSchemaStmt: ALTER TABLE relation_expr SET SCHEMA name' => 'TABLE',
        'AlterObjectSchemaStmt: ALTER TABLE IF_P EXISTS relation_expr SET SCHEMA name' => 'TABLE',
        'AlterObjectSchemaStmt: ALTER STATISTICS any_name SET SCHEMA name' => 'STATISTICS',
        'AlterObjectSchemaStmt: ALTER TEXT_P SEARCH PARSER any_name SET SCHEMA name' => 'TEXT SEARCH PARSER',
        'AlterObjectSchemaStmt: ALTER TEXT_P SEARCH DICTIONARY any_name SET SCHEMA name' => 'TEXT SEARCH DICTIONARY',
        'AlterObjectSchemaStmt: ALTER TEXT_P SEARCH TEMPLATE any_name SET SCHEMA name' => 'TEXT SEARCH TEMPLATE',
        'AlterObjectSchemaStmt: ALTER TEXT_P SEARCH CONFIGURATION any_name SET SCHEMA name' => 'TEXT SEARCH CONFIGURATION',
        'AlterObjectSchemaStmt: ALTER SEQUENCE qualified_name SET SCHEMA name' => 'SEQUENCE',
        'AlterObjectSchemaStmt: ALTER SEQUENCE IF_P EXISTS qualified_name SET SCHEMA name' => 'SEQUENCE',
        'AlterObjectSchemaStmt: ALTER VIEW qualified_name SET SCHEMA name' => 'VIEW',
        'AlterObjectSchemaStmt: ALTER VIEW IF_P EXISTS qualified_name SET SCHEMA name' => 'VIEW',
        'AlterObjectSchemaStmt: ALTER MATERIALIZED VIEW qualified_name SET SCHEMA name' => 'MATERIALIZED VIEW',
        'AlterObjectSchemaStmt: ALTER MATERIALIZED VIEW IF_P EXISTS qualified_name SET SCHEMA name' => 'MATERIALIZED VIEW',
        'AlterObjectSchemaStmt: ALTER FOREIGN TABLE relation_expr SET SCHEMA name' => 'FOREIGN TABLE',
        'AlterObjectSchemaStmt: ALTER FOREIGN TABLE IF_P EXISTS relation_expr SET SCHEMA name' => 'FOREIGN TABLE',
        'AlterObjectSchemaStmt: ALTER TYPE_P any_name SET SCHEMA name' => 'TYPE',
    ];

    /**
     * The kind each OWNER TO production changes.
     */
    private const OWNERS = [
        'AlterOwnerStmt: ALTER AGGREGATE aggregate_with_argtypes OWNER TO RoleSpec' => 'AGGREGATE',
        'AlterOwnerStmt: ALTER COLLATION any_name OWNER TO RoleSpec' => 'COLLATION',
        'AlterOwnerStmt: ALTER CONVERSION_P any_name OWNER TO RoleSpec' => 'CONVERSION',
        'AlterOwnerStmt: ALTER DATABASE name OWNER TO RoleSpec' => 'DATABASE',
        'AlterOwnerStmt: ALTER DOMAIN_P any_name OWNER TO RoleSpec' => 'DOMAIN',
        'AlterOwnerStmt: ALTER FUNCTION function_with_argtypes OWNER TO RoleSpec' => 'FUNCTION',
        'AlterOwnerStmt: ALTER opt_procedural LANGUAGE name OWNER TO RoleSpec' => 'LANGUAGE',
        'AlterOwnerStmt: ALTER LARGE_P OBJECT_P NumericOnly OWNER TO RoleSpec' => 'LARGE OBJECT',
        'AlterOwnerStmt: ALTER OPERATOR operator_with_argtypes OWNER TO RoleSpec' => 'OPERATOR',
        'AlterOwnerStmt: ALTER OPERATOR CLASS any_name USING name OWNER TO RoleSpec' => 'OPERATOR CLASS',
        'AlterOwnerStmt: ALTER OPERATOR FAMILY any_name USING name OWNER TO RoleSpec' => 'OPERATOR FAMILY',
        'AlterOwnerStmt: ALTER PROCEDURE function_with_argtypes OWNER TO RoleSpec' => 'PROCEDURE',
        'AlterOwnerStmt: ALTER ROUTINE function_with_argtypes OWNER TO RoleSpec' => 'ROUTINE',
        'AlterOwnerStmt: ALTER SCHEMA name OWNER TO RoleSpec' => 'SCHEMA',
        'AlterOwnerStmt: ALTER TYPE_P any_name OWNER TO RoleSpec' => 'TYPE',
        'AlterOwnerStmt: ALTER TABLESPACE name OWNER TO RoleSpec' => 'TABLESPACE',
        'AlterOwnerStmt: ALTER STATISTICS any_name OWNER TO RoleSpec' => 'STATISTICS',
        'AlterOwnerStmt: ALTER TEXT_P SEARCH DICTIONARY any_name OWNER TO RoleSpec' => 'TEXT SEARCH DICTIONARY',
        'AlterOwnerStmt: ALTER TEXT_P SEARCH CONFIGURATION any_name OWNER TO RoleSpec' => 'TEXT SEARCH CONFIGURATION',
        'AlterOwnerStmt: ALTER FOREIGN DATA_P WRAPPER name OWNER TO RoleSpec' => 'FOREIGN DATA WRAPPER',
        'AlterOwnerStmt: ALTER SERVER name OWNER TO RoleSpec' => 'SERVER',
        'AlterOwnerStmt: ALTER EVENT TRIGGER name OWNER TO RoleSpec' => 'EVENT TRIGGER',
        'AlterOwnerStmt: ALTER PUBLICATION name OWNER TO RoleSpec' => 'PUBLICATION',
        'AlterOwnerStmt: ALTER SUBSCRIPTION name OWNER TO RoleSpec' => 'SUBSCRIPTION',
    ];

    /**
     * The kind each DEPENDS ON EXTENSION production marks.
     */
    private const DEPENDS = [
        'AlterObjectDependsStmt: ALTER FUNCTION function_with_argtypes opt_no DEPENDS ON EXTENSION name' => 'FUNCTION',
        'AlterObjectDependsStmt: ALTER PROCEDURE function_with_argtypes opt_no DEPENDS ON EXTENSION name' => 'PROCEDURE',
        'AlterObjectDependsStmt: ALTER ROUTINE function_with_argtypes opt_no DEPENDS ON EXTENSION name' => 'ROUTINE',
        'AlterObjectDependsStmt: ALTER TRIGGER name ON qualified_name opt_no DEPENDS ON EXTENSION name' => 'TRIGGER',
        'AlterObjectDependsStmt: ALTER MATERIALIZED VIEW qualified_name opt_no DEPENDS ON EXTENSION name' => 'MATERIALIZED VIEW',
        'AlterObjectDependsStmt: ALTER INDEX qualified_name opt_no DEPENDS ON EXTENSION name' => 'INDEX',
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `AlterObjectSchemaStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function schema(Node $statement): SetSchema
    {
        $form = $this->lowering->productions->form($statement);
        $kind = ObjectKind::from(self::SCHEMAS[$form->signature] ?? throw ImplementationGap::production($form));
        $objects = new ObjectRule($this->lowering);
        [$object] = $objects->altered($form, $objects->start($form));

        return new SetSchema($kind, $object, $this->lowering->names->name($form->node(count($form->node->children) - 1)), $objects->has($form, 'IF_P'));
    }

    /**
     * Lowers `AlterOwnerStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function owner(Node $statement): ChangeOwner
    {
        $form = $this->lowering->productions->form($statement);
        $kind = ObjectKind::from(self::OWNERS[$form->signature] ?? throw ImplementationGap::production($form));
        $objects = new ObjectRule($this->lowering);
        [$object] = $objects->altered($form, $objects->start($form));

        return new ChangeOwner($kind, $object, $this->lowering->roles->role($form->node(count($form->node->children) - 1)));
    }

    /**
     * Lowers `AlterObjectDependsStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function depends(Node $statement): ExtensionDependency
    {
        $form = $this->lowering->productions->form($statement);
        $kind = ObjectKind::from(self::DEPENDS[$form->signature] ?? throw ImplementationGap::production($form));
        $objects = new ObjectRule($this->lowering);
        [$object, $next] = $objects->altered($form, $objects->start($form));
        $no = $this->lowering->productions->form($form->node($next));
        $remove = match ($no->signature) {
            'opt_no: NO' => true,
            'opt_no:' => false,
            default => throw ImplementationGap::production($no),
        };

        return new ExtensionDependency($kind, $object, $this->lowering->names->name($form->node(count($form->node->children) - 1)), $remove);
    }
}
