<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

/**
 * Closed SettingAction alternatives.
 * @visibility public
 */
enum SettingAction: string
{
    case Default = 'default';
    case Assign = 'set';
    case Reset = 'reset';
    case Read = 'read';
    case CopyCurrent = 'from-current';
}
