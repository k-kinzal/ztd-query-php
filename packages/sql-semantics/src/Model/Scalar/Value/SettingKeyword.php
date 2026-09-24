<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Value;

/**

 * @visibility public
 * @example Reading the keyword spelling
 *     \SqlSemantics\Model\Scalar\Value\SettingKeyword::ReadCommitted->value // => 'READ COMMITTED'

 */
enum SettingKeyword: string
{
    case ReadCommitted = 'READ COMMITTED';
    case ReadUncommitted = 'READ UNCOMMITTED';
    case RepeatableRead = 'REPEATABLE READ';
    case Serializable = 'SERIALIZABLE';
    case ReadOnly = 'READ ONLY';
    case ReadWrite = 'READ WRITE';
    case Deferrable = 'DEFERRABLE';
    case NotDeferrable = 'NOT DEFERRABLE';
    case On = 'ON';
    case Off = 'OFF';
    case None = 'NONE';
    case All = 'ALL';
    case Local = 'LOCAL';
    case Default = 'DEFAULT';
    case Document = 'DOCUMENT';
    case Content = 'CONTENT';
}
