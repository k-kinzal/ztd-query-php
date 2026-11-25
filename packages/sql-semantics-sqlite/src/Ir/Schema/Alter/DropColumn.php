<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * The removal of one resolved, eligible column of an ordinary table.
 *
 * @visibility public
 * @example Removing a declared column
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE items (id INTEGER PRIMARY KEY, obsolete TEXT)']);
 *     $semantics->analyze('ALTER TABLE items DROP obsolete', $declarations)->slot // => 1
 */
final class DropColumn implements SemanticsIR
{
    use Snapshot;

    /**
     * The exact declared name of the removed member.
     */
    public readonly Identifier $column;

    /**
     * Whether the selected member has a generation expression.
     */
    public readonly bool $generated;

    /**
     * Validates the selected coordinate within the prior table definition.
     *
     * @param list<DropMember> $heading The prior declared heading
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        array $heading,
        public readonly int $slot,
    ) {
        Check::input($schema->value !== '', 'An ALTER TABLE target needs a nonempty schema identifier.');
        Check::input(!str_starts_with($table->key(), 'sqlite_'), 'A column removal cannot target the reserved SQLite namespace.');
        $members = Check::listOf($heading, DropMember::class, 'A column removal requires an ordered heading with at least two declared members.', 2);
        Check::input(count($members) <= 2000, 'A column removal cannot exceed the 2,000-column profile.');
        Check::input(isset($members[$slot]), 'A column removal must select a declared member.');
        Check::input(!$members[$slot]->protected, 'The selected member has a declaration-local removal restriction.');
        $names = [];
        $ordinary = false;
        foreach ($members as $ordinal => $member) {
            $key = $member->name->key();
            Check::input(!isset($names[$key]), 'A column removal needs distinct declared names.');
            $names[$key] = true;
            $ordinary = $ordinary || ($ordinal !== $slot && !$member->generated);
        }
        Check::input($ordinary, 'A column removal must retain an ordinary column.');
        $this->column = $members[$slot]->name;
        $this->generated = $members[$slot]->generated;
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the absolute table and the name selected by its checked ordinal.
     */
    public function toString(): string
    {
        return 'ALTER TABLE ' . $this->schema->quoted() . '.' . $this->table->quoted()
            . ' DROP COLUMN ' . $this->column->quoted();
    }
}
