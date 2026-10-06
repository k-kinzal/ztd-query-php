<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\AlterFdw;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\AlterForeignServer;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\AlterUserMapping;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\CreateFdw;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\CreateForeignServer;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\CreateUserMapping;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\DropUserMapping;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ImportForeignSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ImportRestriction;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ImportRestrictionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\MappingUser;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ServerVersion;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionRole;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the foreign-data commands.
 *
 * Rule: PG-FOREIGN-LOWER-001. Scope: `CreateFdwStmt`, `AlterFdwStmt`,
 * `fdw_option`, `fdw_options`, `opt_fdw_options`, `CreateForeignServerStmt`,
 * `opt_type`, `foreign_server_version`, `opt_foreign_server_version`,
 * `AlterForeignServerStmt`, `CreateUserMappingStmt`, `auth_ident`,
 * `DropUserMappingStmt`, `AlterUserMappingStmt`, `ImportForeignSchemaStmt`,
 * `import_qualification_type`, `import_qualification`. Constructors: the
 * classes of `Statement\ForeignData` and `FunctionClause`. Termination: lists
 * are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createforeigndatawrapper.html, https://www.postgresql.org/docs/17/sql-createserver.html,
 * https://www.postgresql.org/docs/17/sql-createusermapping.html, https://www.postgresql.org/docs/17/sql-importforeignschema.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class ForeignDataRule
{
    /**
     * The clause each `fdw_option` production lowers to: role and whether a function is named.
     */
    private const FUNCTIONS = [
        'fdw_option: HANDLER handler_name' => [FunctionRole::Handler, true],
        'fdw_option: NO HANDLER' => [FunctionRole::Handler, false],
        'fdw_option: VALIDATOR handler_name' => [FunctionRole::Validator, true],
        'fdw_option: NO VALIDATOR' => [FunctionRole::Validator, false],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a foreign-data command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;
        $options = $this->lowering->options;

        return match ($form->signature) {
            'CreateFdwStmt: CREATE FOREIGN DATA_P WRAPPER name opt_fdw_options create_generic_options' => new CreateFdw($names->name($form->node(4)), $this->functions($form->node(5)), $options->genericOptions($form->node(6))),
            'AlterFdwStmt: ALTER FOREIGN DATA_P WRAPPER name opt_fdw_options alter_generic_options' => new AlterFdw($names->name($form->node(4)), $this->functions($form->node(5)), $options->alteredOptions($form->node(6))),
            'AlterFdwStmt: ALTER FOREIGN DATA_P WRAPPER name fdw_options' => new AlterFdw($names->name($form->node(4)), $this->functions($form->node(5))),
            'CreateForeignServerStmt: CREATE SERVER name opt_type opt_foreign_server_version FOREIGN DATA_P WRAPPER name create_generic_options' => new CreateForeignServer($names->name($form->node(2)), false, $this->type($form->node(3)), $this->version($form->node(4)), $names->name($form->node(8)), $options->genericOptions($form->node(9))),
            'CreateForeignServerStmt: CREATE SERVER IF_P NOT EXISTS name opt_type opt_foreign_server_version FOREIGN DATA_P WRAPPER name create_generic_options' => new CreateForeignServer($names->name($form->node(5)), true, $this->type($form->node(6)), $this->version($form->node(7)), $names->name($form->node(11)), $options->genericOptions($form->node(12))),
            'AlterForeignServerStmt: ALTER SERVER name foreign_server_version alter_generic_options' => new AlterForeignServer($names->name($form->node(2)), $this->version($form->node(3)), $options->alteredOptions($form->node(4))),
            'AlterForeignServerStmt: ALTER SERVER name foreign_server_version' => new AlterForeignServer($names->name($form->node(2)), $this->version($form->node(3))),
            'AlterForeignServerStmt: ALTER SERVER name alter_generic_options' => new AlterForeignServer($names->name($form->node(2)), null, $options->alteredOptions($form->node(3))),
            'CreateUserMappingStmt: CREATE USER MAPPING FOR auth_ident SERVER name create_generic_options' => new CreateUserMapping(false, $this->user($form->node(4)), $names->name($form->node(6)), $options->genericOptions($form->node(7))),
            'CreateUserMappingStmt: CREATE USER MAPPING IF_P NOT EXISTS FOR auth_ident SERVER name create_generic_options' => new CreateUserMapping(true, $this->user($form->node(7)), $names->name($form->node(9)), $options->genericOptions($form->node(10))),
            'DropUserMappingStmt: DROP USER MAPPING FOR auth_ident SERVER name' => new DropUserMapping(false, $this->user($form->node(4)), $names->name($form->node(6))),
            'DropUserMappingStmt: DROP USER MAPPING IF_P EXISTS FOR auth_ident SERVER name' => new DropUserMapping(true, $this->user($form->node(6)), $names->name($form->node(8))),
            'AlterUserMappingStmt: ALTER USER MAPPING FOR auth_ident SERVER name alter_generic_options' => new AlterUserMapping($this->user($form->node(4)), $names->name($form->node(6)), $options->alteredOptions($form->node(7))),
            'ImportForeignSchemaStmt: IMPORT_P FOREIGN SCHEMA name import_qualification FROM SERVER name INTO name create_generic_options' => new ImportForeignSchema($names->name($form->node(3)), $this->restriction($form->node(4)), $names->name($form->node(7)), $names->name($form->node(9)), $options->genericOptions($form->node(10))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_fdw_options` or `fdw_options`.
     *
     * @return list<FunctionClause>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function functions(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        $items = match ($form->signature) {
            'opt_fdw_options:' => [],
            'opt_fdw_options: fdw_options' => $this->lowering->items($form->node(0), 'fdw_options: fdw_option', 'fdw_options: fdw_options fdw_option'),
            default => $this->lowering->items($list, 'fdw_options: fdw_option', 'fdw_options: fdw_options fdw_option'),
        };
        $functions = [];
        foreach ($items as $item) {
            $option = $this->lowering->productions->form($item);
            [$role, $named] = self::FUNCTIONS[$option->signature] ?? throw ImplementationGap::production($option);
            $functions[] = new FunctionClause($role, $named ? (new ExtensionRule($this->lowering))->handler($option->node(1)) : null);
        }

        return $functions;
    }

    /**
     * Lowers `opt_type`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function type(Node $type): ?StringConstant
    {
        $form = $this->lowering->productions->form($type);

        return match ($form->signature) {
            'opt_type: TYPE_P Sconst' => $this->lowering->literals->string($form->node(1)),
            'opt_type:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_foreign_server_version` or `foreign_server_version`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function version(Node $version): ?ServerVersion
    {
        $form = $this->lowering->productions->form($version);

        return match ($form->signature) {
            'opt_foreign_server_version: foreign_server_version' => $this->version($form->node(0)),
            'opt_foreign_server_version:' => null,
            'foreign_server_version: VERSION_P Sconst' => new ServerVersion($this->lowering->literals->string($form->node(1))),
            'foreign_server_version: VERSION_P NULL_P' => new ServerVersion(),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `auth_ident`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function user(Node $user): RoleSpec|MappingUser
    {
        $form = $this->lowering->productions->form($user);

        return match ($form->signature) {
            'auth_ident: RoleSpec' => $this->lowering->roles->role($form->node(0)),
            'auth_ident: USER' => MappingUser::User,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `import_qualification`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function restriction(Node $restriction): ?ImportRestriction
    {
        $form = $this->lowering->productions->form($restriction);
        if ($form->signature === 'import_qualification:') {
            return null;
        }
        if ($form->signature !== 'import_qualification: import_qualification_type ( relation_expr_list )') {
            throw ImplementationGap::production($form);
        }
        $type = $this->lowering->productions->form($form->node(0));
        $kind = match ($type->signature) {
            'import_qualification_type: LIMIT TO' => ImportRestrictionKind::LimitTo,
            'import_qualification_type: EXCEPT' => ImportRestrictionKind::Except,
            default => throw ImplementationGap::production($type),
        };

        return new ImportRestriction($kind, $this->lowering->queries->relations($form->node(2)));
    }
}
