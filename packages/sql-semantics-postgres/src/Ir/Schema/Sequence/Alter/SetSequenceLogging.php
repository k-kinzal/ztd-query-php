<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Changes the native logging mode of a resolved sequence.
 * @visibility public
 * @example Setting a declared sequence's storage mode
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $source = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector(new $name('ticket'), new $name('public'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLogging($source, [\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging::Unlogged]))->toString() // => 'ALTER SEQUENCE "public"."ticket" SET UNLOGGED'
 */
final class SetSequenceLogging implements SemanticsIR
{
    use Snapshot;

    /**
     * @var list<SequenceLogging>
     */
    public readonly array $modes;

    /**
     * Checks the ordered logging assignments and detaches their list.
     *
     * @param list<SequenceLogging> $modes
     */
    public function __construct(
        public readonly SequenceSelector $source,
        array $modes,
        public readonly bool $ifExists = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $source->checkProfile($release);
        if ($source->presence === SequencePresence::Existing) {
            Check::input($source->schema?->value !== 'pg_temp', 'Changing sequence logging requires a persistent source.');
            Check::input($modes !== [], 'A present sequence requires at least one logging assignment.');
        } else {
            Check::input($ifExists, 'An absent sequence selector requires IF EXISTS.');
            Check::input($modes === [], 'An absent sequence cannot carry logging assignments.');
        }
        $detached = Check::listOf($modes, SequenceLogging::class, 'Sequence logging needs an ordered list of modes.');
        foreach ($detached as $offset => $mode) {
            Check::input(
                $offset + 1 === count($detached) || $mode === $detached[0],
                'A sequence logging prefix must request one consistent mode.'
            );
        }
        $this->modes = $detached;
    }

    /**
     * Identifies the exact native release and lexical profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits each requested assignment in order.
     */
    public function toString(): string
    {
        $commands = [];
        foreach ($this->modes as $mode) {
            $commands[] = 'SET ' . $mode->value;
        }

        return 'ALTER SEQUENCE ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->source->quoted() . ' '
            . ($commands === [] ? 'SET LOGGED' : implode(', ', $commands));
    }
}
