<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\StockIndexNames;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Removes an ordered list of checked sequence selectors.
 *
 * @visibility public
 * @example Removing a persistent sequence
 *     $target = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceDropTarget(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('ticket'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('public'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequence([$target]))->toString() // => 'DROP SEQUENCE "public"."ticket" RESTRICT'
 */
final class DropSequence implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<SequenceDropTarget> Exact native selector order
     */
    public readonly array $targets;

    /**
     * Copies and checks the resolved target roles before the removal can be used.
     * @param list<SequenceDropTarget> $targets
     */
    public function __construct(
        array $targets,
        public readonly bool $ifExists = false,
        public readonly bool $cascade = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $checked = Check::listOf($targets, SequenceDropTarget::class, 'Sequence removal requires a nonempty list of checked selectors.', 1);
        $seen = [];
        $namespaces = [];
        foreach ($checked as $target) {
            $presence = $target->presence;
            Check::input($presence === SequenceDropPresence::Existing || $ifExists, 'An absent sequence removal target requires IF EXISTS.');
            $namespace = $target->schema?->value;
            Check::input(
                !StockIndexNames::contains($release, new Name($namespace ?? 'pg_catalog'), new Name($target->name->value)),
                'A stock non-sequence relation cannot be a sequence removal target.'
            );
            $key = ($namespace ?? '') . "\0" . $target->name->value;
            Check::input(!isset($seen[$key]) || $seen[$key] === $presence, 'Sequence removal cannot disagree about a named target.');
            $seen[$key] = $presence;
            if ($namespace !== null) {
                $present = $presence !== SequenceDropPresence::MissingNamespace;
                Check::input(
                    !isset($namespaces[$namespace]) || $namespaces[$namespace] === $present,
                    'Sequence removal cannot disagree about a named namespace.'
                );
                $namespaces[$namespace] = $present;
            }
        }
        $this->targets = $checked;
    }

    /**
     * Identifies the native release and lexical profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the native target order and both removal policies.
     */
    public function toString(): string
    {
        $pieces = [];
        foreach ($this->targets as $target) {
            $pieces[] = $target->quoted();
        }

        return 'DROP SEQUENCE ' . ($this->ifExists ? 'IF EXISTS ' : '') . implode(', ', $pieces)
            . ($this->cascade ? ' CASCADE' : ' RESTRICT');
    }
}
