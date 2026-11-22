<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A native removal of named persistent tables.
 *
 * @visibility public
 * @example Reading the identity of a table to remove
 *     $identity = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('app'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('accounts'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\DropTable([$identity]))->targets[0]->table->value // => 'accounts'
 */
final class DropTable implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<TableName> Targets in statement order
     */
    public readonly array $targets;

    /**
     * Closes a table removal over an ordered list of distinct identities.
     *
     * @param list<TableName> $targets
     */
    public function __construct(
        array $targets,
        public readonly bool $ifExists = false,
        public readonly bool $cascade = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $names = Check::listOf($targets, TableName::class, 'DROP TABLE needs a nonempty target list.', 1);
        $seen = [];
        foreach ($names as $target) {
            $key = $target->quoted();
            Check::input(!isset($seen[$key]), 'DROP TABLE cannot repeat a target.');
            $seen[$key] = true;
        }
        $this->targets = $names;
    }

    /**
     * Identifies the fixed native release and lexical settings.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
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

        return 'DROP TABLE ' . ($this->ifExists ? 'IF EXISTS ' : '')
            . implode(', ', $names)
            . ($this->cascade ? ' CASCADE' : ' RESTRICT');
    }
}
