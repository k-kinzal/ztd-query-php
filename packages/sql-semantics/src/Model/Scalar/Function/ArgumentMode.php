<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

/**

 * @visibility public

 */
enum ArgumentMode: string
{
    case All = 'ALL';
    case Distinct = 'DISTINCT';
}
