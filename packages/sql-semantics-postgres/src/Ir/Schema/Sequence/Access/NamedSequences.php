<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An ordered list of existing sequences selected for a privilege change.
 * @visibility public
 * @example Targeting a public sequence
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $selector = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector(new $name('ticket'), new $name('public'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\NamedSequences([$selector]))->toString() // => 'SEQUENCE "public"."ticket"'
 */
final class NamedSequences
{
    use Snapshot;

    /**
     * @var non-empty-list<SequenceSelector>
     */
    public readonly array $sequences;

    /**
     * Detaches the list and rejects absent targets.
     * @param list<SequenceSelector> $sequences
     */
    public function __construct(array $sequences)
    {
        $list = Check::listOf($sequences, SequenceSelector::class, 'Sequence privileges require named sequence targets.', 1);
        foreach ($list as $sequence) {
            Check::input($sequence->presence === SequencePresence::Existing, 'Sequence privileges require an existing named sequence.');
        }
        $this->sequences = $list;
    }

    /**
     * Checks the release's concrete stock occupancy for every target.
     */
    public function checkRelease(GrammarRelease $release): void
    {
        foreach ($this->sequences as $sequence) {
            $sequence->checkProfile($release);
        }
    }

    /**
     * Emits the exact selected sequence identities in order.
     */
    public function toString(): string
    {
        $names = [];
        foreach ($this->sequences as $sequence) {
            $names[] = $sequence->quoted();
        }

        return 'SEQUENCE ' . implode(', ', $names);
    }
}
