<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Instance;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseOption;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DropDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName;
use SqlSemantics\Statement\Statement;

/**
 * Lowers CREATE, ALTER and DROP DATABASE.
 *
 * Rule: MYSQL-DATABASE-001. Scope: the DATABASE alternatives of create,
 * alter and drop (alter and drop in 5.6 and 5.7), alter_database_stmt,
 * drop_database_stmt, opt_create_database_options, create_database_options,
 * create_database_option, alter_database_options, alter_database_option,
 * default_encryption. The character set and collation go through the leaf
 * character set rules; DEFAULT before an option and its equals sign are
 * optional (LeafNoise). Constructs: CreateDatabase, AlterDatabase,
 * UpgradeDatabaseName, DropDatabase and the DatabaseOption classes.
 * Terminates: the option lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-database.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-database.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class DatabaseRule
{
    /**
     * The option list productions: absent lists and list spines.
     */
    private const LISTS = [
        'opt_create_database_options:', 'create_database_options: create_database_option', 'create_database_options: create_database_options create_database_option',
        'alter_database_options: alter_database_option', 'alter_database_options: alter_database_options alter_database_option',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a database statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        $names = $this->lowering->names;
        $options = $this->lowering->options;

        return match ($form->signature) {
            'create: CREATE DATABASE opt_if_not_exists ident opt_create_database_options' => new CreateDatabase(
                $options->present($form->node(2)),
                $names->identifier($form->node(3)),
                $this->options($form->node(4)),
            ),
            'alter: ALTER DATABASE ident_or_empty create_database_options', 'alter_database_stmt: ALTER DATABASE ident_or_empty alter_database_options' => new AlterDatabase(
                $names->optional($form->node(2)),
                $this->options($form->node(3)),
            ),
            'alter: ALTER DATABASE ident UPGRADE_SYM DATA_SYM DIRECTORY_SYM NAME_SYM' => new UpgradeDatabaseName($names->identifier($form->node(2))),
            'drop: DROP DATABASE if_exists ident', 'drop_database_stmt: DROP DATABASE if_exists ident' => new DropDatabase($options->present($form->node(2)), $names->identifier($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an option list: a node of `opt_create_database_options`, `create_database_options` or `alter_database_options`.
     *
     * @return list<DatabaseOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'opt_create_database_options: create_database_options') {
            $list = $form->node(0);
            $form = $this->lowering->form($list);
        }
        $this->lowering->names->claimed($form, self::LISTS);
        $options = [];
        foreach ((new Lists())->items($list) as $item) {
            $options[] = $this->option($item);
        }

        return $options;
    }

    /**
     * Lowers one option: a node of `create_database_option` or `alter_database_option`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function option(Node $option): DatabaseOption
    {
        $form = $this->lowering->form($option);
        $charsets = $this->lowering->charsets;

        return match ($form->signature) {
            'alter_database_option: create_database_option' => $this->option($form->node(0)),
            'alter_database_option: READ_SYM ONLY_SYM opt_equal ternary_option' => $this->readOnly($form),
            'create_database_option: default_charset' => new DatabaseCharset($charsets->charset($form->node(0))),
            'create_database_option: default_collation' => new DatabaseCollation($charsets->collation($form->node(0)) ?? new CollationName(null)),
            'create_database_option: default_encryption' => $this->encryption($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers READ ONLY.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function readOnly(Form $form): DatabaseReadOnly
    {
        $this->lowering->options->present($form->node(2));

        return new DatabaseReadOnly($this->lowering->numbers->ternary($form->node(3)));
    }

    /**
     * Lowers ENCRYPTION: a node of `default_encryption`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function encryption(Node $encryption): DatabaseEncryption
    {
        $form = $this->lowering->form($encryption);
        if ($form->signature !== 'default_encryption: opt_default ENCRYPTION_SYM opt_equal TEXT_STRING_sys') {
            throw ImplementationGap::production($form);
        }
        $this->lowering->options->present($form->node(0));
        $this->lowering->options->present($form->node(2));

        return new DatabaseEncryption($this->lowering->literals->text($form->node(3)));
    }
}
