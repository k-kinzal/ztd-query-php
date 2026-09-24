<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

/**
 * Closed SettingScope alternatives.
 * @visibility public
 * @example Reading the lifetime of an assignment
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $binder->bind('SET LOCAL search_path TO public, other')->settings[0]->scope // => \SqlSemantics\Model\Configuration\SettingScope::Local
 *     $binder->bind("SET SESSION work_mem = '4MB'")->settings[0]->scope // => \SqlSemantics\Model\Configuration\SettingScope::Session
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
