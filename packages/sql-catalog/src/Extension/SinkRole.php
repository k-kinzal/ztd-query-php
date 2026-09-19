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
    case Prepare = 'prepare';
    case Execute = 'execute';
    case Bind = 'bind';

    /**
     * Whether the call carries the statement text itself.
     */
    public function carriesSql(): bool
    {
        return match ($this) {
            self::Query, self::Prepare => true,
            self::Execute, self::Bind => false,
        };
    }
}
