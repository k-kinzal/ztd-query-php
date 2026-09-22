<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json;

/**
 * A classified SQL/JSON quotes policy.
 * @visibility public
 */
enum Quotes: string
{
    case Default = '';
    case Keep = 'KEEP QUOTES';
    case Omit = 'OMIT QUOTES';
}
