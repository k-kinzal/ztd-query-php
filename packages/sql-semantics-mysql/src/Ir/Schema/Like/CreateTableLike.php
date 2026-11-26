<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Like;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Creates an empty table using the native definition of a resolved source table.
 *
 * @visibility public
 * @example Reading both operands of a definition copy
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE app.original (n INT)']);
 *     $value = $semantics->analyze('CREATE TEMPORARY TABLE app.scratch LIKE app.original', $declarations);
 *     [$value->destination->kind->value, $value->source->name->table->value] // => ['temporary-table', 'original']
 */
final class CreateTableLike implements SemanticsIR
{
    use Snapshot;

    /**
     * Closes a copy over distinct absolute source and destination coordinates.
     */
    public function __construct(
        public readonly TableIdentity $destination,
        public readonly TableIdentity $source,
        public readonly bool $ifNotExists = false,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
    ) {
        Check::input($destination->quoted() !== $source->quoted(), 'CREATE TABLE LIKE requires distinct source and destination names.');
    }

    /**
     * Identifies the fixed native release and name profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native';
    }

    /**
     * Writes the copy with a qualified destination and a qualified source.
     */
    public function toString(): string
    {
        return 'CREATE ' . ($this->destination->kind === ExternalRelationKind::TemporaryTable ? 'TEMPORARY ' : '')
            . 'TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->destination->quoted()
            . ' LIKE ' . $this->source->quoted();
    }
}
