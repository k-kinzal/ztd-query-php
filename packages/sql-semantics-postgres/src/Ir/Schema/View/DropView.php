<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete removal of persistent views of one kind.
 *
 * @visibility public
 * @example Resolving a removal of a declared materialized view
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $catalog = $semantics->context(['CREATE MATERIALIZED VIEW report AS SELECT 1 AS n']);
 *     $semantics->analyze('DROP MATERIALIZED VIEW report CASCADE', $catalog)->toString() // => 'DROP MATERIALIZED VIEW "public"."report" CASCADE'
 */
final class DropView implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ViewName> The targets in statement order
     */
    public readonly array $targets;

    /**
     * Checks the distinct persistent identities before the removal value is returned.
     * @param list<ViewName> $targets The ordered targets of the selected view kind
     */
    public function __construct(
        public readonly ViewRemovalKind $kind,
        array $targets,
        public readonly bool $ifExists = false,
        public readonly bool $cascade = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $names = Check::listOf($targets, ViewName::class, 'DROP VIEW needs a nonempty target list.', 1);
        $seen = [];
        foreach ($names as $target) {
            $key = $target->quoted();
            Check::input(!isset($seen[$key]), 'DROP VIEW cannot repeat a target.');
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
     * Emits the view kind, exact targets, and selected behavior.
     */
    public function toString(): string
    {
        $names = [];
        foreach ($this->targets as $target) {
            $names[] = $target->quoted();
        }

        return 'DROP ' . $this->kind->value . ' ' . ($this->ifExists ? 'IF EXISTS ' : '')
            . implode(', ', $names) . ($this->cascade ? ' CASCADE' : ' RESTRICT');
    }
}
