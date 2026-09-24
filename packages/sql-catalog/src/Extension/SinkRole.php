<?php

declare(strict_types=1);

namespace SqlCatalog\Extension;

/**
 * What a matched database call does with the statement it is given.
 *
 * @visibility root
 */
enum SinkRole: string
{
    case Query = 'query';
    case Compose = 'compose';
    case Prepare = 'prepare';
    case Execute = 'execute';
    case Bind = 'bind';
    case Builder = 'builder';

    /**
     * Whether the call hands the statement text back rather than sending it.
     *
     * Some interfaces interpolate a statement and return it for something else
     * to run. Treating the interpolation as the statement's source, rather than
     * as a call the analyzer cannot see through, is what keeps the statement
     * readable where it is finally issued.
     */
    public function returnsSql(): bool
    {
        return $this === self::Compose;
    }

    /**
     * Whether the call carries the statement text itself.
     */
    public function carriesSql(): bool
    {
        return match ($this) {
            self::Query, self::Compose, self::Prepare => true,
            self::Execute, self::Bind, self::Builder => false,
        };
    }
}
