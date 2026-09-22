<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public

 */
enum FrameUnit: string
{
    case Rows = 'ROWS';
    case Range = 'RANGE';
    case Groups = 'GROUPS';
}
