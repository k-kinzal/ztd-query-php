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
use SqlSemantics\Platform\MySql\Statement\Statement;

/**
 * Lowers CREATE, ALTER and DROP DATABASE.
 *
 * Rule: MYSQL-DATABASE-001. Scope: the DATABASE alternatives of create,
 * alter and drop (alter and drop in 5.6 and 5.7), alter_database_stmt,
 * drop_database_stmt, opt_create_database_options, create_database_options,
 * create_database_option, alter_database_options, alter_database_option,
 * default_encryption. The character set and collation go through the leaf
 * character set rules; DEFAULT before an option and its equals sign are
 * retained. Constructs: CreateDatabase, AlterDatabase,
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
                databaseWord: \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord::from(strtoupper($form->token(1)->text)),
            ),
            'alter: ALTER DATABASE ident_or_empty create_database_options', 'alter_database_stmt: ALTER DATABASE ident_or_empty alter_database_options' => new AlterDatabase(
                $names->optional($form->node(2)),
                $this->options($form->node(3)),
                databaseWord: \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord::from(strtoupper($form->token(1)->text)),
            ),
            'alter: ALTER DATABASE ident UPGRADE_SYM DATA_SYM DIRECTORY_SYM NAME_SYM' => $this->upgrade($form),
            'drop: DROP DATABASE if_exists ident', 'drop_database_stmt: DROP DATABASE if_exists ident' => new DropDatabase($options->present($form->node(2)), $names->identifier($form->node(3)), databaseWord: \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord::from(strtoupper($form->token(1)->text))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Separates the legacy directory marker from its decoded name component.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When the name has no legacy directory interpretation
     */
    public function upgrade(Form $form): UpgradeDatabaseName
    {
        $name = $this->lowering->names->decoded($form->node(2));
        $suffix = substr($name->value, 9);
        if (!str_starts_with($name->value, '#mysql50#') || !\SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName::valid($suffix)) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidUpgradeName($name), (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0, false);
        }

        $legacy = $this->lowering->origins->record(new \SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName(new \SqlSemantics\Statement\Identifier\Name($suffix)), $form->node(2));

        return new UpgradeDatabaseName($this->lowering->leaves->record($legacy), databaseWord: \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseWord::from(strtoupper($form->token(1)->text)));
    }

    /**
     * Lowers an option list: a node of `opt_create_database_options`, `create_database_options` or `alter_database_options`.
     *
     * @return list<DatabaseOption>
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a READ ONLY operand is outside its domain
     * @throws \SqlSemantics\Construction\RejectedInput When an encryption operand is outside its domain
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
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a READ ONLY operand is outside its domain
     * @throws \SqlSemantics\Construction\RejectedInput When an encryption operand is outside its domain
     * @throws ImplementationGap When a production has no rule
     */
    public function option(Node $option): DatabaseOption
    {
        $form = $this->lowering->form($option);
        $charsets = $this->lowering->charsets;

        return match ($form->signature) {
            'alter_database_option: create_database_option' => $this->option($form->node(0)),
            'alter_database_option: READ_SYM ONLY_SYM opt_equal ternary_option' => $this->readOnly($form),
            'create_database_option: default_charset' => new DatabaseCharset($charsets->charset($form->node(0)), equals: $this->lowering->options->present($this->lowering->form($form->node(0))->node(2)), defaultKeyword: $this->lowering->options->present($this->lowering->form($form->node(0))->node(0)), form: (new \SqlSemantics\Platform\MySql\Lowering\Type\TypePartRule($this->lowering))->named($this->lowering->form($form->node(0))->node(1))),
            'create_database_option: default_collation' => new DatabaseCollation($charsets->collation($form->node(0)) ?? new CollationName(null), equals: $this->lowering->options->present($this->lowering->form($form->node(0))->node(2)), defaultKeyword: $this->lowering->options->present($this->lowering->form($form->node(0))->node(0))),
            'create_database_option: default_encryption' => $this->encryption($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers READ ONLY.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Diagnostic\AnalysisException When the value converts to neither 0 nor 1
     */
    public function readOnly(Form $form): DatabaseReadOnly
    {
        $equals = $this->lowering->options->present($form->node(2));
        $value = $this->lowering->numbers->ternary($form->node(3));
        if ($value !== null && !(new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->atMost($value, '1')) {
            throw new \SqlSemantics\Diagnostic\AnalysisException('Syntax error: READ ONLY accepts 0, 1 or DEFAULT.', 0, new \SqlParser\Parser\SyntaxException($form->node(3)->tokens()[0], [], ''));
        }

        return new DatabaseReadOnly($value, equals: $equals);
    }

    /**
     * Lowers ENCRYPTION: a node of `default_encryption`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When the input does not denote Y or N
     */
    public function encryption(Node $encryption): DatabaseEncryption
    {
        $form = $this->lowering->form($encryption);
        if ($form->signature !== 'default_encryption: opt_default ENCRYPTION_SYM opt_equal TEXT_STRING_sys') {
            throw ImplementationGap::production($form);
        }
        $default = $this->lowering->options->present($form->node(0));
        $equals = $this->lowering->options->present($form->node(2));

        $text = $this->lowering->literals->bytes($form->node(3));
        $value = \SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption::fromOperand($text);
        if ($value === null) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption(new \SqlSemantics\Platform\MySql\Statement\Literal\Text($text)), (new \SqlSemantics\Construction\SourceRanges())->span($encryption)[1] ?? 0);
        }

        $spelling = $text === $value->value ? null : $this->lowering->leaves->record(new \SqlSemantics\Platform\MySql\Statement\Literal\Text($text));

        return new DatabaseEncryption($this->lowering->leaves->record($value), $spelling, equals: $equals, defaultKeyword: $default);
    }
}
