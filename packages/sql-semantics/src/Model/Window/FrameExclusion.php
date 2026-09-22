<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public

 */
enum FrameExclusion: string
{
    case None = 'NO OTHERS';
    case Current = 'CURRENT ROW';
    case Group = 'GROUP';
    case Ties = 'TIES';
}
