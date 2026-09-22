<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Query;

/**

 * @visibility public

 */
enum Quantifier: string
{
    case All = 'ALL';
    case Any = 'ANY';
    case Some = 'SOME';
}
