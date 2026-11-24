<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An ordered PostgreSQL request to remove resolved namespaces.
 *
 * @visibility public
 * @example Removing an application namespace and its dependents
 *     $schema = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaDropTarget(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('archive'), true);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchema([$schema], cascade: true))->toString() // => 'DROP SCHEMA "archive" CASCADE'
 */
final class DropSchema implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<SchemaDropTarget>
     */
    public readonly array $targets;

    /**
     * Fixes the namespace order, absence guard and dependency policy.
     *
     * @param list<SchemaDropTarget> $targets
     */
    public function __construct(
        array $targets,
        public readonly bool $ifExists = false,
        public readonly bool $cascade = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $checked = Check::listOf($targets, SchemaDropTarget::class, 'A schema removal requires a nonempty ordered selector list.', 1);
        $presence = [];
        foreach ($checked as $target) {
            Check::input($target->present || $ifExists, 'An absent schema removal target requires IF EXISTS.');
            $name = $target->name->value;
            Check::input(!isset($presence[$name]) || $presence[$name] === $target->present, 'A schema removal cannot disagree about a named target.');
            $presence[$name] = $target->present;
        }
        $this->targets = $checked;
    }

    /**
     * Identifies the fixed PostgreSQL profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the named namespaces and both dependency policies.
     */
    public function toString(): string
    {
        $names = [];
        foreach ($this->targets as $target) {
            $names[] = $target->name->quoted();
        }

        return 'DROP SCHEMA ' . ($this->ifExists ? 'IF EXISTS ' : '') . implode(', ', $names) . ($this->cascade ? ' CASCADE' : ' RESTRICT');
    }
}
