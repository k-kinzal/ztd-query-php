<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Text;

/**
 * Where a value the analyzer could not pin down comes from.
 *
 * The origin drives how a gap in reconstructed SQL is reported: a fragment
 * traced back to a request superglobal is a different finding from one that
 * merely crossed a call the analyzer declined to follow.
 *
 * @visibility root
 */
enum Origin: string
{
    case External = 'external';
    case Parameter = 'parameter';
    case Property = 'property';
    case Call = 'call';
    case Budget = 'budget';
    case Unreached = 'unreached';
    case Loop = 'loop';
    case Branch = 'branch';
    case Unresolved = 'unresolved';

    /**
     * How strongly a gap of this origin suggests attacker-controlled input.
     */
    public function isExternallyControlled(): bool
    {
        return $this === self::External;
    }

    /**
     * A short phrase naming the origin in reports.
     */
    public function describe(): string
    {
        return match ($this) {
            self::External => 'external input',
            self::Parameter => 'a function parameter',
            self::Property => 'an object property',
            self::Call => 'a call the analyzer did not follow',
            self::Budget => 'a dependency the analyzer stopped following',
            self::Unreached => 'a call the analyzer never examined',
            self::Loop => 'a value built by a loop',
            self::Branch => 'values that differ between branches',
            self::Unresolved => 'an expression the analyzer could not resolve',
        };
    }
}
