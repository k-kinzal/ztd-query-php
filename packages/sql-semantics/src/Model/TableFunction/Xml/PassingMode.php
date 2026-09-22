<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Xml;

/**
 * The requested XML argument passing mode.
 * @visibility public
 */
enum PassingMode: string
{
    case Default = '';
    case Reference = 'BY REF';
    case Value = 'BY VALUE';
}
