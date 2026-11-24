<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Reconfigures the generator selected by its resolved native namespace.
 * @visibility public
 * @example Restarting at the recorded start of the selected sequence
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $source = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector(new $name('ticket'), new $name('public'));
 *     $changes = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceChanges(restart: \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceResetOperand::Native);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ReconfigureSequence($source, $changes))->toString() // => 'ALTER SEQUENCE "public"."ticket" RESTART'
 */
final class ReconfigureSequence implements SemanticsIR
{
    use Snapshot;

    /**
     * Checks the named owner against the selected namespace or the guarded absence role.
     */
    public function __construct(
        public readonly SequenceSelector $source,
        public readonly ?SequenceChanges $changes,
        public readonly bool $ifExists = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $source->checkProfile($release);
        if ($source->presence === SequencePresence::Existing) {
            Check::input($changes !== null, 'A present sequence requires checked changes.');
            $owner = $changes->owner;
            Check::input(
                !$owner instanceof SequenceOwner || $owner->schema->value === $source->schema?->value,
                'The sequence and its owning column must share a namespace.'
            );
        } else {
            Check::input($ifExists, 'An absent sequence selector requires IF EXISTS.');
            Check::input($changes === null, 'An absent sequence cannot carry changes.');
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
     * Renders the chosen assignments or the syntactic operand of the guarded no-op.
     */
    public function toString(): string
    {
        return 'ALTER SEQUENCE ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->source->quoted()
            . ($this->changes?->toString() ?? ' RESTART');
    }
}
