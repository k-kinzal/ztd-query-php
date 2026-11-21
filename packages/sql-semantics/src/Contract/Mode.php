<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

/**
 * The session settings of one database that change how SQL text is read.
 *
 * @visibility public
 * @example Serializing a database session mode
 *     $mode = new class implements \SqlSemantics\Contract\Mode { public function toString(): string { return 'ANSI_QUOTES'; } };
 *     $mode->toString() // => 'ANSI_QUOTES'
 */
interface Mode
{
    /**
     * Answers the settings in the canonical spelling the language profile records.
     */
    public function toString(): string;
}
