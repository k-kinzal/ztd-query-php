<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View\SchemaViewOrder;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\StockRoles;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A standalone PostgreSQL namespace declaration and its owner.
 *
 * @visibility public
 * @example Declaring a namespace owned by the effective role
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('archive')))->toString() // => 'CREATE SCHEMA "archive" AUTHORIZATION CURRENT_USER'
 */
final class CreateSchema implements SemanticsIR
{
    use Snapshot;

    /**
     * The decoded namespace name, or null to derive it from the session owner.
     */
    public readonly ?Identifier $name;

    /**
     * @var list<CreateTable> Checked table members in native execution order
     */
    public readonly array $tables;

    /**
     * @var list<CreateSequence> Checked sequence members in the first native phase
     */
    public readonly array $sequences;

    /**
     * @var list<CreateView> Checked views after the sequence and table phases
     */
    public readonly array $views;

    /**
     * Fixes the namespace coordinate, owner, and duplicate handling.
     *
     * @param list<CreateTable> $tables
     * @param list<CreateSequence> $sequences
     * @param list<CreateView> $views
     */
    public function __construct(
        ?Identifier $name,
        public readonly Identifier|SchemaSessionRole $owner = SchemaSessionRole::Effective,
        public readonly bool $ifNotExists = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        array $tables = [],
        array $sequences = [],
        array $views = [],
    ) {
        if ($owner instanceof Identifier) {
            Check::input($owner->value !== 'public' && $owner->value !== 'none', 'A named schema owner needs a single role name.');
            Check::input(!str_starts_with($owner->value, 'pg_') || StockRoles::contains($release, $owner->value), 'The stock schema owner must exist in this release.');
            $name ??= $owner;
        }
        Check::input($name === null || !str_starts_with($name->value, 'pg_'), 'A declared schema name cannot start with pg_.');
        $checkedSequences = Check::listOf($sequences, CreateSequence::class, 'Schema sequences are an ordered list of checked sequence declarations.');
        $checkedTables = Check::listOf($tables, CreateTable::class, 'Schema tables are an ordered list of checked table declarations.');
        $checkedViews = Check::listOf($views, CreateView::class, 'Schema views are an ordered list of checked view declarations.');
        SchemaRelationOrder::check($name, $ifNotExists, $release, $checkedSequences, $checkedTables);
        SchemaViewOrder::check($name, $ifNotExists, $release, $checkedSequences, $checkedTables, $checkedViews);
        $this->name = $name;
        $this->sequences = $checkedSequences;
        $this->tables = $checkedTables;
        $this->views = $checkedViews;
    }

    /**
     * Identifies the selected PostgreSQL language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the checked namespace and its fixed authorization expression.
     */
    public function toString(): string
    {
        $name = $this->name === null ? '' : $this->name->quoted() . ' ';
        $owner = $this->owner instanceof Identifier ? $this->owner->quoted() : $this->owner->value;
        $elements = '';
        foreach ($this->sequences as $sequence) {
            $elements .= ' ' . $sequence->toString();
        }
        foreach ($this->tables as $table) {
            $elements .= ' ' . $table->toString();
        }
        foreach ($this->views as $view) {
            $elements .= ' ' . $view->toString();
        }

        return 'CREATE SCHEMA ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $name . 'AUTHORIZATION ' . $owner . $elements;
    }
}
