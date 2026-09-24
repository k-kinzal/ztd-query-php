<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

/**
 * The declared time zone interpretation of a temporal type.
 * @visibility public
 * @example Classifying time zone behavior
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a TIMESTAMP WITH TIME ZONE, b TIMESTAMP)')->tables[0];
 *     $table->columns[0]->type->identity->timeZone // => \SqlSemantics\Type\Identity\TimeZoneMode::With
 *     $table->columns[0]->type->name // => 'timestamptz'
 *     $table->columns[1]->type->identity->timeZone // => \SqlSemantics\Type\Identity\TimeZoneMode::Unspecified
 */
enum TimeZoneMode: string
{
    case Unspecified = '';
    case Without = 'WITHOUT TIME ZONE';
    case With = 'WITH TIME ZONE';
}
