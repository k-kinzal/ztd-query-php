<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

/**
 * Closed SettingScope alternatives.
 * @visibility public
 */
enum SettingScope: string
{
    case Session = 'session';
    case Local = 'local';
    case Global = 'global';
    case Persist = 'persist';
    case PersistOnly = 'persist-only';
    case User = 'user';
    case Database = 'database';
}
