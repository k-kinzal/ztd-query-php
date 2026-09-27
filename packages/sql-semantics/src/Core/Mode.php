<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

/**
 * Session settings of a database that change how it reads SQL text.
 *
 * A server can read the same text differently from one session to another,
 * for example when a setting turns double quotes into identifier quotes or
 * makes a backslash an ordinary character in a string. A database package
 * that has such settings provides its Mode; analysis and composition read
 * and write SQL under it. A database without such settings has none.
 *
 * @visibility public
 * @example Accepting the mode of any database
 *     $describe = static fn (\SqlSemantics\Core\Mode $mode): string => $mode->toString();
 *     $describe instanceof \Closure // => true
 */
interface Mode
{
    /**
     * Spells the settings as the session reports them.
     */
    public function toString(): string;
}
