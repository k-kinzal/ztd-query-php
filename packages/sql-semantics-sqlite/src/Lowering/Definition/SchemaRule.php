<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterAddColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterDropColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateIndex;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateView;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;
use SqlSemantics\Platform\Sqlite\Statement\Schema\SchemaObjectKind;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Statement;

/**
 * Lowers view and index definitions, DROP and ALTER TABLE.
 *
 * Rule: SQLITE-SCHEMA-LOWER-001. Scope: the `cmd` productions of CREATE VIEW,
 * CREATE INDEX, DROP TABLE, DROP VIEW, DROP INDEX, DROP TRIGGER and ALTER
 * TABLE, with uniqueflag, ifexists, add_column_fullname and kwcolumn_opt.
 * Constructors: CreateView, CreateIndex, Drop, AlterRenameTable,
 * AlterAddColumn, AlterDropColumn, AlterRenameColumn. UNIQUE, TEMP, IF EXISTS
 * and IF NOT EXISTS are model values; the COLUMN keyword of ALTER TABLE is
 * declared noise. Terminates: fixed number of children.
 * Source: https://sqlite.org/lang_createview.html, https://sqlite.org/lang_createindex.html,
 * https://sqlite.org/lang_droptable.html, https://sqlite.org/lang_altertable.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SchemaRule
{
    /**
     * The kind of object each DROP production removes.
     */
    private const DROPS = [
        'cmd: DROP TABLE ifexists fullname' => SchemaObjectKind::Table,
        'cmd: DROP VIEW ifexists fullname' => SchemaObjectKind::View,
        'cmd: DROP INDEX ifexists fullname' => SchemaObjectKind::Index,
        'cmd: DROP TRIGGER ifexists fullname' => SchemaObjectKind::Trigger,
    ];

    private readonly CreateTableRule $tables;

    private readonly ColumnRule $columns;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->tables = new CreateTableRule($lowering);
        $this->columns = new ColumnRule($lowering);
    }

    /**
     * Lowers a schema command, or answers null for a command of another family.
     */
    public function command(Form $form): ?Statement
    {
        if (isset(self::DROPS[$form->signature])) {
            return new Drop(self::DROPS[$form->signature], $this->lowering->names->qualified($form->node(3)), $this->ifExists($form->node(2)));
        }

        return match ($form->signature) {
            'cmd: createkw temp VIEW ifnotexists nm dbnm eidlist_opt AS select' => $this->view($form),
            'cmd: createkw uniqueflag INDEX ifnotexists nm dbnm ON nm LP sortlist RP where_opt' => $this->index($form),
            'cmd: ALTER TABLE fullname RENAME TO nm' => new AlterRenameTable($this->lowering->names->qualified($form->node(2)), $this->lowering->names->name($form->node(5))),
            'cmd: ALTER TABLE add_column_fullname ADD kwcolumn_opt columnname carglist' => new AlterAddColumn(
                $this->added($form->node(2)),
                $this->columns->column($this->keyword($form->node(4), $form->node(5)), $form->node(6)),
            ),
            'cmd: ALTER TABLE fullname DROP kwcolumn_opt nm' => new AlterDropColumn($this->lowering->names->qualified($form->node(2)), $this->lowering->names->name($this->keyword($form->node(4), $form->node(5)))),
            'cmd: ALTER TABLE fullname RENAME kwcolumn_opt nm TO nm' => new AlterRenameColumn(
                $this->lowering->names->qualified($form->node(2)),
                $this->lowering->names->name($this->keyword($form->node(4), $form->node(5))),
                $this->lowering->names->name($form->node(7)),
            ),
            default => null,
        };
    }

    /**
     * Lowers a view definition.
     */
    public function view(Form $form): CreateView
    {
        $this->tables->created($form->node(0));
        $temporary = $this->lowering->flags->temporary($form->node(1));
        $ifNotExists = $this->lowering->flags->ifNotExists($form->node(3));
        $name = $this->lowering->names->scoped($form->node(4), $form->node(5));
        $columns = $this->lowering->ordering->optionalColumns($form->node(6));

        return new CreateView($name, $this->lowering->selects->select($form->node(8)), $columns, $temporary, $ifNotExists);
    }

    /**
     * Lowers an index definition.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function index(Form $form): CreateIndex
    {
        $this->tables->created($form->node(0));
        $flag = $this->lowering->productions->form($form->node(1));
        $unique = match ($flag->signature) {
            'uniqueflag:' => false,
            'uniqueflag: UNIQUE' => true,
            default => throw ImplementationGap::production($flag),
        };
        $ifNotExists = $this->lowering->flags->ifNotExists($form->node(3));
        $name = $this->lowering->names->scoped($form->node(4), $form->node(5));
        $table = $this->lowering->names->name($form->node(7));
        $terms = $this->lowering->ordering->terms($form->node(9));

        return new CreateIndex($name, $table, $terms, $this->lowering->expressions->where($form->node(11)), $unique, $ifNotExists);
    }

    /**
     * Lowers the optional IF EXISTS clause.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function ifExists(Node $clause): bool
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'ifexists:' => false,
            'ifexists: IF EXISTS' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the table name of ALTER TABLE ADD COLUMN.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function added(Node $name): QualifiedName
    {
        $form = $this->lowering->productions->form($name);

        return match ($form->signature) {
            'add_column_fullname: fullname' => $this->lowering->names->qualified($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Checks the optional COLUMN keyword and answers the node that follows it.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function keyword(Node $keyword, Node $following): Node
    {
        $form = $this->lowering->productions->form($keyword);

        return match ($form->signature) {
            'kwcolumn_opt:', 'kwcolumn_opt: COLUMNKW' => $following,
            default => throw ImplementationGap::production($form),
        };
    }
}
