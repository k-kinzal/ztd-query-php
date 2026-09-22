<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public

 */
enum Direction: string
{
    case Preceding = 'PRECEDING';
    case Following = 'FOLLOWING';
}
