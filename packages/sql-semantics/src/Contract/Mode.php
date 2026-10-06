<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

/**
 * The session settings of one database that change how SQL text is read.
 *
 * @visibility public
 * @example Naming the lexical settings of a MySQL session
 *     \SqlSemantics\Platform\MySql\Mode::fromString('ANSI_QUOTES')->toString() // => 'ANSI_QUOTES'
 */
interface Mode
{
    /**
     * Answers the settings in the canonical spelling the language profile records.
     */
    public function toString(): string;
}
