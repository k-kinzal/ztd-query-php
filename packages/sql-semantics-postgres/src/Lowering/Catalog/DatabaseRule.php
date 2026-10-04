<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\AlterDatabase;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\AlterDatabaseSetting;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\CreateDatabase;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DatabaseOption;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DatabaseOptionKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DefaultSetting;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DropDatabase;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DropDatabaseOption;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\MoveDatabase;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\RefreshDatabaseCollation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace\AlterTablespaceOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace\CreateTablespace;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace\DropTablespace;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the database and tablespace commands.
 *
 * Rule: PG-DATABASE-LOWER-001. Scope: `CreatedbStmt`, `createdb_opt_list`,
 * `createdb_opt_items`, `createdb_opt_item`, `createdb_opt_name`,
 * `opt_equal`, `AlterDatabaseStmt`, `AlterDatabaseSetStmt`, `DropdbStmt`,
 * `drop_option_list`, `drop_option`, `CreateTableSpaceStmt`,
 * `OptTableSpaceOwner`, `DropTableSpaceStmt`, `AlterTblSpcStmt`.
 * Constructors: `CreateDatabase`, `AlterDatabase`, `MoveDatabase`,
 * `RefreshDatabaseCollation`, `AlterDatabaseSetting`, `DropDatabase`,
 * `DatabaseOption`, `CreateTablespace`, `DropTablespace`,
 * `AlterTablespaceOptions`. The optional `=` of a database option and the
 * optional WITH before database options are noise (CatalogNoise).
 * Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createdatabase.html, https://www.postgresql.org/docs/17/sql-alterdatabase.html,
 * https://www.postgresql.org/docs/17/sql-dropdatabase.html, https://www.postgresql.org/docs/17/sql-createtablespace.html,
 * https://www.postgresql.org/docs/17/sql-altertablespace.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class DatabaseRule
{
    /**
     * The option each keyword spelling of `createdb_opt_name` names.
     */
    private const KEYWORDS = [
        'createdb_opt_name: CONNECTION LIMIT' => DatabaseOptionKeyword::ConnectionLimit,
        'createdb_opt_name: ENCODING' => DatabaseOptionKeyword::Encoding,
        'createdb_opt_name: LOCATION' => DatabaseOptionKeyword::Location,
        'createdb_opt_name: OWNER' => DatabaseOptionKeyword::Owner,
        'createdb_opt_name: TABLESPACE' => DatabaseOptionKeyword::Tablespace,
        'createdb_opt_name: TEMPLATE' => DatabaseOptionKeyword::Template,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a database or tablespace command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'CreatedbStmt: CREATE DATABASE name opt_with createdb_opt_list' => new CreateDatabase($names->name($form->node(2)), $this->options($form->node(4))),
            'AlterDatabaseStmt: ALTER DATABASE name WITH createdb_opt_list' => new AlterDatabase($names->name($form->node(2)), $this->options($form->node(4))),
            'AlterDatabaseStmt: ALTER DATABASE name createdb_opt_list' => new AlterDatabase($names->name($form->node(2)), $this->options($form->node(3))),
            'AlterDatabaseStmt: ALTER DATABASE name SET TABLESPACE name' => new MoveDatabase($names->name($form->node(2)), $names->name($form->node(5))),
            'AlterDatabaseStmt: ALTER DATABASE name REFRESH COLLATION VERSION_P' => new RefreshDatabaseCollation($names->name($form->node(2))),
            'AlterDatabaseSetStmt: ALTER DATABASE name SetResetClause' => new AlterDatabaseSetting($names->name($form->node(2)), $this->lowering->utilities->setReset($form->node(3))),
            'DropdbStmt: DROP DATABASE name' => new DropDatabase($names->name($form->node(2))),
            'DropdbStmt: DROP DATABASE IF_P EXISTS name' => new DropDatabase($names->name($form->node(4)), true),
            'DropdbStmt: DROP DATABASE name opt_with ( drop_option_list )' => new DropDatabase($names->name($form->node(2)), false, $this->dropOptions($form->node(5))),
            'DropdbStmt: DROP DATABASE IF_P EXISTS name opt_with ( drop_option_list )' => new DropDatabase($names->name($form->node(4)), true, $this->dropOptions($form->node(7))),
            'CreateTableSpaceStmt: CREATE TABLESPACE name OptTableSpaceOwner LOCATION Sconst opt_reloptions' => new CreateTablespace($names->name($form->node(2)), $this->owner($form->node(3)), $this->lowering->literals->string($form->node(5)), $this->lowering->options->definitions($form->node(6))),
            'DropTableSpaceStmt: DROP TABLESPACE name' => new DropTablespace($names->name($form->node(2))),
            'DropTableSpaceStmt: DROP TABLESPACE IF_P EXISTS name' => new DropTablespace($names->name($form->node(4)), true),
            'AlterTblSpcStmt: ALTER TABLESPACE name SET reloptions' => new AlterTablespaceOptions($names->name($form->node(2)), false, $this->lowering->options->definitions($form->node(4))),
            'AlterTblSpcStmt: ALTER TABLESPACE name RESET reloptions' => new AlterTablespaceOptions($names->name($form->node(2)), true, $this->lowering->options->definitions($form->node(4))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `createdb_opt_list`; an empty list has no options.
     *
     * @return list<DatabaseOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function options(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'createdb_opt_list:') {
            return [];
        }
        if ($form->signature !== 'createdb_opt_list: createdb_opt_items') {
            throw ImplementationGap::production($form);
        }
        $options = [];
        foreach ($this->lowering->items($form->node(0), 'createdb_opt_items: createdb_opt_item', 'createdb_opt_items: createdb_opt_items createdb_opt_item') as $item) {
            $options[] = $this->option($item);
        }

        return $options;
    }

    /**
     * Lowers `createdb_opt_item`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function option(Node $item): DatabaseOption
    {
        $form = $this->lowering->productions->form($item);
        $this->equal($form->node(1));
        $name = $this->lowering->productions->form($form->node(0));
        $option = match ($name->signature) {
            'createdb_opt_name: IDENT' => $this->lowering->names->token($name->token(0)),
            default => self::KEYWORDS[$name->signature] ?? throw ImplementationGap::production($name),
        };

        return new DatabaseOption($option, match ($form->signature) {
            'createdb_opt_item: createdb_opt_name opt_equal NumericOnly' => $this->lowering->literals->signed($form->node(2)),
            'createdb_opt_item: createdb_opt_name opt_equal opt_boolean_or_string' => $this->lowering->options->value($form->node(2)),
            'createdb_opt_item: createdb_opt_name opt_equal DEFAULT' => DefaultSetting::Default,
            default => throw ImplementationGap::production($form),
        });
    }

    /**
     * Accepts `opt_equal`, whose `=` is noise.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function equal(Node $equal): void
    {
        $form = $this->lowering->productions->form($equal);
        if ($form->signature !== 'opt_equal: =' && $form->signature !== 'opt_equal:') {
            throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers `drop_option_list`.
     *
     * @return list<DropDatabaseOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function dropOptions(Node $list): array
    {
        $options = [];
        foreach ($this->lowering->items($list, 'drop_option_list: drop_option', 'drop_option_list: drop_option_list , drop_option') as $item) {
            $form = $this->lowering->productions->form($item);
            $options[] = $form->signature === 'drop_option: FORCE' ? DropDatabaseOption::Force : throw ImplementationGap::production($form);
        }

        return $options;
    }

    /**
     * Lowers `OptTableSpaceOwner`; no owner is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function owner(Node $owner): ?\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec
    {
        $form = $this->lowering->productions->form($owner);

        return match ($form->signature) {
            'OptTableSpaceOwner: OWNER RoleSpec' => $this->lowering->roles->role($form->node(1)),
            'OptTableSpaceOwner:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
