<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\DropDependency\DropTableDependencies;
use SqlSemantics\Platform\MySql\Ir\Schema\DropDependency\TableDropReference;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\AbsentDropTarget;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A native table removal with ordered absolute targets and explicit namespace and absence policies.
 *
 * @visibility public
 * @example Reading the identity of a table to remove
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName(new \SqlSemantics\Platform\MySql\Ir\Identifier('app'), new \SqlSemantics\Platform\MySql\Ir\Identifier('accounts'));
 *     $identity = new \SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity($name, \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::TemporaryTable);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\DropTable([$identity], [], temporaryOnly: true))->toString() // => 'DROP TEMPORARY TABLE `app`.`accounts`'
 */
final class DropTable implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<TableIdentity|AbsentDropTarget> Targets in statement order
     */
    public readonly array $targets;

    /**
     * Closes a table removal over an ordered list of distinct identities.
     *
     * @param array<array-key, mixed> $targets An ordered list of checked identities or permitted absences
     * @param list<TableDropReference> $references Complete native incoming edges for the persistent targets
     */
    public function __construct(
        array $targets,
        array $references,
        public readonly bool $ifExists = false,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly bool $temporaryOnly = false,
    ) {
        Check::input(array_is_list($targets) && $targets !== [], 'DROP TABLE needs a nonempty target list.');
        $seen = [];
        $checked = [];
        foreach ($targets as $target) {
            Check::input($target instanceof TableIdentity || $target instanceof AbsentDropTarget, 'DROP TABLE requires checked target identities.');
            if ($target instanceof AbsentDropTarget) {
                Check::input($ifExists, 'An absent DROP TABLE target requires IF EXISTS.');
            } elseif ($temporaryOnly) {
                Check::input($target->kind === ExternalRelationKind::TemporaryTable, 'DROP TEMPORARY TABLE requires session-local targets.');
            }
            $key = $target->quoted();
            Check::input(!isset($seen[$key]), 'DROP TABLE cannot repeat a target.');
            $seen[$key] = true;
            $checked[] = $target;
        }
        $dependencies = Check::listOf($references, TableDropReference::class, 'DROP TABLE requires a checked native dependency list.');
        (new DropTableDependencies())->check($checked, $dependencies, $release);
        $this->targets = $checked;
    }

    /**
     * Identifies the fixed native release and lexical settings.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/foreign-key-checks=1';
    }

    /**
     * Emits the removal and its explicit target and behavior.
     */
    public function toString(): string
    {
        $names = [];
        foreach ($this->targets as $target) {
            $names[] = $target->quoted();
        }

        return 'DROP ' . ($this->temporaryOnly ? 'TEMPORARY ' : '') . 'TABLE ' . ($this->ifExists ? 'IF EXISTS ' : '')
            . implode(', ', $names);
    }
}
