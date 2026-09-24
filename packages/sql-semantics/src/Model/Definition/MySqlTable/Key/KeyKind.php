<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Key;

/**
 * The kind of named index or constraint an alteration addresses; CHECK and CONSTRAINT exist from MySQL 8.0.
 * @visibility public
 * @example Reading the dropped kind
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t DROP FOREIGN KEY fk');
 *     $statement->alterations[0]->kind // => \SqlSemantics\Model\Definition\MySqlTable\Key\KeyKind::ForeignKey
 */
enum KeyKind: string
{
    case Index = 'INDEX';
    case ForeignKey = 'FOREIGN KEY';
    case Check = 'CHECK';
    case Constraint = 'CONSTRAINT';
}
