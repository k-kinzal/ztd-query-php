<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Changes a sequence's local name within its resolved native namespace.
 *
 * @visibility public
 * @example Renaming a sequence in the current schema
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $source = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector(new $name('ticket'), new $name('public'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\RenameSequence($source, new $name('receipt')))->toString() // => 'ALTER SEQUENCE "public"."ticket" RENAME TO "receipt"'
 */
final class RenameSequence implements SemanticsIR
{
    use Snapshot;

    /**
     * Checks the selector and the destination before publication.
     */
    public function __construct(
        public readonly SequenceSelector $source,
        public readonly Identifier $destination,
        public readonly bool $ifExists = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $source->checkProfile($release);
        if ($source->presence === SequencePresence::Existing) {
            Check::input($source->name->value !== $destination->value, 'A present sequence rename requires a distinct destination.');
        } else {
            Check::input($ifExists, 'An absent sequence selector requires IF EXISTS.');
        }
    }

    /**
     * Identifies the exact native release and lexical profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the closed native name change.
     */
    public function toString(): string
    {
        return 'ALTER SEQUENCE ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->source->quoted() . ' RENAME TO ' . $this->destination->quoted();
    }
}
