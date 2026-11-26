<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\StockIndexNames;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Removes resolved indexes with PostgreSQL's locking, presence and dependency policies.
 *
 * @visibility public
 * @example Rendering a checked index removal
 *     $target = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\MissingIndex(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('idx'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex([$target], true))->toString() // => 'DROP INDEX IF EXISTS "idx" RESTRICT'
 */
final class DropIndex implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<IndexReference|MissingIndex> Target occurrences in statement order
     */
    public readonly array $targets;

    /**
     * Checks the removal mode and copies the ordered target occurrences.
     *
     * @param array<array-key, mixed> $targets Ordered occurrences of IndexReference and MissingIndex
     */
    public function __construct(
        array $targets,
        public readonly bool $ifExists = false,
        public readonly bool $cascade = false,
        public readonly IndexDropMode $mode = IndexDropMode::Ordinary,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        Check::input(array_is_list($targets) && $targets !== [], 'DROP INDEX needs an ordered nonempty target list.');
        Check::input($mode === IndexDropMode::Ordinary || (count($targets) === 1 && !$cascade), 'DROP INDEX CONCURRENTLY needs one target and RESTRICT.');
        $copy = [];
        $names = [];
        $tables = [];
        $schemas = [];
        foreach ($targets as $target) {
            Check::input($target instanceof IndexReference || ($target instanceof MissingIndex && $ifExists), 'DROP INDEX needs ordinary bound indexes or guarded absence.');
            $identity = $target->quoted();
            $owner = $target instanceof IndexReference ? $target->table->value : false;
            Check::input(!isset($names[$identity]) || $names[$identity] === $owner, 'Repeated index targets must agree on identity and presence.');
            $names[$identity] = $owner;
            if ($target instanceof IndexReference) {
                $tables[$target->schema->quoted() . '.' . $target->table->quoted()] = true;
            }
            if ($target->schema !== null) {
                $exists = $target instanceof IndexReference || !$target->missingSchema;
                $schema = $target->schema->value;
                Check::input(!isset($schemas[$schema]) || $schemas[$schema] === $exists, 'Targets must agree on whether their schema exists.');
                $schemas[$schema] = $exists;
                if ($target instanceof MissingIndex) {
                    Check::input(!StockIndexNames::contains($release, new Name($schema), new Name($target->index->value)), 'A known stock relation cannot be an absent index.');
                }
            }
            $copy[] = $target;
        }
        Check::input(array_intersect_key($names, $tables) === [], 'An indexed table cannot also be an index selector in the same schema.');
        $this->targets = $copy;
    }

    /**
     * Identifies the fixed native language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits each checked target occurrence under the represented native policies.
     */
    public function toString(): string
    {
        $names = [];
        foreach ($this->targets as $target) {
            $names[] = $target->quoted();
        }

        return 'DROP INDEX ' . ($this->mode === IndexDropMode::Concurrently ? 'CONCURRENTLY ' : '') . ($this->ifExists ? 'IF EXISTS ' : '')
            . implode(', ', $names) . ($this->cascade ? ' CASCADE' : ' RESTRICT');
    }
}
