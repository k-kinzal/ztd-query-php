<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Storage;

/**
 * A storage parameter named without a value, which PostgreSQL reads as enabling the option.
 * @visibility public
 * @example Inspecting an option named without a value
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TABLE t(id integer) WITH (autovacuum_enabled)');
 *     $statement->definition->properties->storageParameters[0]->value === \SqlSemantics\Schema\Storage\ImpliedSetting::Enabled // => true
 */
enum ImpliedSetting: string
{
    case Enabled = 'enabled';
}
