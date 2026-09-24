<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Trigger;

/**
 * The database event that fires an event trigger.
 * @visibility public
 * @example Naming the event of an event trigger
 *     \SqlSemantics\Model\Definition\Trigger\EventTriggerEvent::from('sql_drop') // => \SqlSemantics\Model\Definition\Trigger\EventTriggerEvent::SqlDrop
 */
enum EventTriggerEvent: string
{
    case DdlCommandStart = 'ddl_command_start';
    case DdlCommandEnd = 'ddl_command_end';
    case SqlDrop = 'sql_drop';
    case TableRewrite = 'table_rewrite';
    case Login = 'login';
}
