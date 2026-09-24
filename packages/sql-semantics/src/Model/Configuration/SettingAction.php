<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

/**
 * Closed SettingAction alternatives.
 * @visibility public
 * @example Deriving the effect from the bound setting form
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind("SET SESSION work_mem = '4MB'")->settings[0]->action // => \SqlSemantics\Model\Configuration\SettingAction::Assign
 *     $binder->bind('RESET work_mem')->setting->action // => \SqlSemantics\Model\Configuration\SettingAction::Reset
 *     $binder->bind('SET work_mem TO DEFAULT')->settings[0]->action // => \SqlSemantics\Model\Configuration\SettingAction::Default
 */
enum SettingAction: string
{
    case Default = 'default';
    case Assign = 'set';
    case Reset = 'reset';
    case Read = 'read';
    case CopyCurrent = 'from-current';
}
