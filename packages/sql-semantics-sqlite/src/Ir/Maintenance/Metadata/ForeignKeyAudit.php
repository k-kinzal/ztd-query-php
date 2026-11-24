<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A native SQLite foreign-key audit of one resolved schema or relation.
 *
 * @visibility public
 * @example Auditing all children in the main database
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\ForeignKeyAudit())->toString() // => 'PRAGMA "main".foreign_key_check'
 */
final class ForeignKeyAudit implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the audit target and explicit access for an optional builtin catalog.
     * @throws InvalidConstruction When a catalog scope contradicts the target role or schema
     */
    public function __construct(
        public readonly Identifier|RelationTarget|CatalogTable $target = new Identifier('main'),
        public readonly ?Identifier $catalogAccess = null,
    ) {
        Check::input(
            $catalogAccess === null || ($target instanceof CatalogTable && $target->schema->key() === $catalogAccess->key()),
            'An explicit foreign-key audit catalog access must match the selected builtin catalog.'
        );
        if ($target instanceof CatalogTable && $catalogAccess === null) {
            $schema = $target->schema->key();
            Check::input($schema === 'main' || $schema === 'temp', 'An implicit foreign-key audit cannot select an attached builtin catalog.');
        }
        if ($target instanceof RelationTarget) {
            Check::input(!(new CatalogAliases())->isCatalogName($target->name), 'An internal SQLite schema table requires a catalog role.');
        }
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the absolute audit scope or the exact access to a builtin catalog.
     */
    public function toString(): string
    {
        $target = $this->target;
        if ($target instanceof CatalogTable) {
            $qualifier = $this->catalogAccess === null ? '' : $this->catalogAccess->quoted() . '.';

            return 'PRAGMA ' . $qualifier . 'foreign_key_check(' . $target->name()->quoted() . ')';
        }
        if ($target instanceof RelationTarget) {
            return 'PRAGMA ' . $target->schema->quoted() . '.foreign_key_check(' . $target->name->quoted() . ')';
        }

        return 'PRAGMA ' . $target->quoted() . '.foreign_key_check';
    }
}
