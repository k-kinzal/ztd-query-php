<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Declares a checked native sequence and its initial generator state.
 *
 * @visibility public
 * @example Constructing a sequence in a declared schema
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceName(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('app'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('ticket'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence($name))->profileId() // => 'pg-17.2/utf8/standard-conforming-strings/native'
 */
final class CreateSequence implements SemanticsIR
{
    use Snapshot;

    /**
     * Combines the native identity, storage and generator policy.
     */
    public function __construct(
        public readonly SequenceName $name,
        public readonly SequenceParameters $parameters = new SequenceParameters(),
        public readonly SequencePersistence $persistence = SequencePersistence::Permanent,
        public readonly bool $ifNotExists = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        public readonly ?SequenceOwner $owner = null,
    ) {
        Check::input(
            ($name->schema->value === 'pg_temp') === $persistence->temporary(),
            'A sequence namespace must agree with its temporary storage.'
        );
        Check::input(
            $owner === null || $owner->schema->value === $name->schema->value,
            'A sequence owner must belong to the same namespace.'
        );
    }

    /**
     * Identifies the native release and lexical settings.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits every effective generator option and the separately resolved initial state.
     */
    public function toString(): string
    {
        $storage = $this->persistence->value === '' ? '' : $this->persistence->value . ' ';
        $numbers = $this->parameters;
        $restart = $numbers->initial->decimal === $numbers->start->decimal ? '' : ' RESTART WITH ' . $numbers->initial->decimal;

        return 'CREATE ' . $storage . 'SEQUENCE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->name->quoted()
            . ' AS ' . $numbers->storage->value . ' INCREMENT BY ' . $numbers->increment->decimal
            . ' MINVALUE ' . $numbers->minimum->decimal . ' MAXVALUE ' . $numbers->maximum->decimal
            . ' START WITH ' . $numbers->start->decimal . $restart . ' CACHE ' . $numbers->cache->decimal
            . ($numbers->cycle ? ' CYCLE' : ' NO CYCLE')
            . ($this->owner === null ? '' : ' OWNED BY ' . $this->owner->quoted());
    }
}
