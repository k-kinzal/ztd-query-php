<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogAliases;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A native SQLite integrity audit with resolved access and target.
 *
 * @visibility public
 * @example Auditing a single attached database with the quick algorithm
 *     $schema = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('archive');
 *     $kind = \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityKind::Quick;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAudit($kind, schema: $schema))->toString() // => 'PRAGMA "archive".quick_check'
 */
final class IntegrityAudit implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the mode, report budget or bound relation, and native database access.
     * @throws InvalidConstruction When a resolved target contradicts its role or access scope
     */
    public function __construct(
        public readonly IntegrityKind $kind,
        public readonly IntegrityLimit|RelationTarget|CatalogTable $target = new IntegrityLimit(),
        public readonly ?Identifier $schema = null,
    ) {
        if (!$target instanceof IntegrityLimit) {
            Check::input($schema === null || $schema->key() === $target->schema->key(), 'An integrity audit target must belong to its explicit schema.');
            if ($target instanceof RelationTarget) {
                Check::input(!(new CatalogAliases())->isCatalogName($target->name), 'An internal SQLite schema table requires a catalog role.');
            } elseif ($schema === null) {
                $key = $target->schema->key();
                Check::input($key === 'main' || $key === 'temp', 'An implicit integrity audit cannot select an attached builtin catalog.');
            }
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
     * Emits the native audit while preserving implicit or explicit access.
     */
    public function toString(): string
    {
        $target = $this->target;
        $qualifier = $this->schema === null ? '' : $this->schema->quoted() . '.';
        $argument = match (true) {
            $target instanceof IntegrityLimit => $target->value === IntegrityLimit::DEFAULT_VALUE ? '' : '(' . $target->value . ')',
            $target instanceof RelationTarget => '(' . $target->name->quoted() . ')',
            $target instanceof CatalogTable => '(' . $target->name()->quoted() . ')',
        };

        return 'PRAGMA ' . $qualifier . $this->kind->value . $argument;
    }
}
