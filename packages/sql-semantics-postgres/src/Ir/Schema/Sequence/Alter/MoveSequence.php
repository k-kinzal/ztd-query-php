<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Moves one resolved sequence to a persistent namespace.
 * @visibility public
 * @example Moving the existing sequence named ticket into the archive schema
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $source = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector(new $name('ticket'), new $name('public'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\MoveSequence($source, new $name('archive')))->toString() // => 'ALTER SEQUENCE "public"."ticket" SET SCHEMA "archive"'
 */
final class MoveSequence implements SemanticsIR
{
    use Snapshot;

    /**
     * Checks the persistent move or the guarded no-op before publication.
     */
    public function __construct(
        public readonly SequenceSelector $source,
        public readonly ?Identifier $destination,
        public readonly bool $ifExists = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $source->checkProfile($release);
        if ($source->presence === SequencePresence::Existing) {
            Check::input($source->schema?->value !== 'pg_temp', 'A sequence move requires a persistent source namespace.');
            Check::input($destination !== null, 'A present sequence move requires a destination namespace.');
            Check::input(
                $destination->value !== 'pg_temp' && $destination->value !== 'pg_toast' && !IndexSchemas::sessionInternal($destination->value),
                'A sequence move requires a persistent destination outside TOAST.'
            );
        } else {
            Check::input($ifExists, 'An absent sequence selector requires IF EXISTS.');
            Check::input($destination === null, 'An absent sequence move has no destination namespace.');
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
     * Emits the target spelling required by the syntax of a guarded no-op.
     */
    public function toString(): string
    {
        return 'ALTER SEQUENCE ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->source->quoted()
            . ' SET SCHEMA ' . ($this->destination?->quoted() ?? '"pg_catalog"');
    }
}
