<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A name change for one user column in a persistent PostgreSQL table.
 *
 * @visibility public
 * @example Retaining the descendant policy
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE reports (id integer)']);
 *     $semantics->analyze('ALTER TABLE ONLY reports RENAME COLUMN id TO report_id', $declarations)->only // => true
 */
final class RenameColumn implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixed system attributes of ordinary native tables.
     */
    private const SYSTEM_COLUMNS = ['tableoid', 'xmin', 'cmin', 'xmax', 'cmax', 'ctid'];

    /**
     * Fixes both user-column identifiers and the descent and absence policies.
     */
    public function __construct(
        public readonly TableName $target,
        public readonly Identifier $column,
        public readonly Identifier $destination,
        public readonly bool $only = false,
        public readonly bool $ifExists = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        Check::input($ifExists || $column->value !== $destination->value, 'A column rename requires a distinct destination name.');
        Check::input(
            !in_array($column->value, self::SYSTEM_COLUMNS, true) && !in_array($destination->value, self::SYSTEM_COLUMNS, true),
            'A column rename requires user-column identifiers.',
        );
    }

    /**
     * Identifies the fixed native PostgreSQL profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the exact scoped member and both name positions.
     */
    public function toString(): string
    {
        return 'ALTER TABLE ' . ($this->ifExists ? 'IF EXISTS ' : '') . ($this->only ? 'ONLY ' : '') . $this->target->quoted()
            . ' RENAME COLUMN ' . $this->column->quoted() . ' TO ' . $this->destination->quoted();
    }
}
