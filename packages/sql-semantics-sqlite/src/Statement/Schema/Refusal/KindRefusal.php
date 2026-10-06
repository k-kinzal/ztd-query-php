<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal;

use SqlSemantics\Statement\Declaration\RelationKind;

/**
 * The requests SQLite carries out only for one kind of relation.
 *
 * Each case names the request; its value says what the request needs. A
 * view refuses DROP TABLE, every ALTER TABLE form, CREATE INDEX, BEFORE and
 * AFTER triggers and an upsert; a table refuses DROP VIEW and INSTEAD OF
 * triggers. Release 3.47.2 reports, in order of the cases: "use DROP VIEW to
 * delete view", "use DROP TABLE to delete table", "view may not be altered",
 * "Cannot add a column to a view", "cannot drop column from view", "cannot
 * rename columns of view", "views may not be indexed", "cannot create BEFORE
 * trigger on view", "cannot create AFTER trigger on view", "cannot create
 * INSTEAD OF trigger on table" and "cannot UPSERT a view" (`build.c`,
 * `alter.c`, `trigger.c`, `insert.c`).
 * Source: https://sqlite.org/lang_droptable.html, https://sqlite.org/lang_dropview.html,
 * https://sqlite.org/lang_altertable.html, https://sqlite.org/lang_createindex.html,
 * https://sqlite.org/lang_createtrigger.html#instead_of_triggers, https://sqlite.org/lang_upsert.html.
 *
 * @visibility public
 * @example Reading the relation kind a request needs
 *     \SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal::DropView->required() // => \SqlSemantics\Statement\Declaration\RelationKind::View
 */
enum KindRefusal: string
{
    case DropTable = 'DROP TABLE removes only a table.';
    case DropView = 'DROP VIEW removes only a view.';
    case RenameTable = 'ALTER TABLE renames only a table.';
    case AddColumn = 'ALTER TABLE adds a column only to a table.';
    case DropColumn = 'ALTER TABLE drops a column only from a table.';
    case RenameColumn = 'ALTER TABLE renames a column only of a table.';
    case CreateIndex = 'CREATE INDEX indexes only a table.';
    case BeforeTrigger = 'A BEFORE trigger watches only a table.';
    case AfterTrigger = 'An AFTER trigger watches only a table.';
    case InsteadOfTrigger = 'An INSTEAD OF trigger watches only a view.';
    case Upsert = 'An upsert writes only to a table.';

    /**
     * Answers the kind of relation the request needs.
     */
    public function required(): RelationKind
    {
        return $this === self::DropView || $this === self::InsteadOfTrigger ? RelationKind::View : RelationKind::BaseTable;
    }
}
