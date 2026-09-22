<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction;

/**

 * Optional transaction settings refer to the current environment when omitted. @visibility public

 */
final class Characteristics
{
    /**
     * Records transaction settings; omitted settings refer to the current environment.
     */
    public function __construct(public readonly ?Isolation $isolation = null, public readonly ?Access $access = null, public readonly ?bool $deferrable = null, public readonly bool $consistentSnapshot = false)
    {
    }
}
