<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog\Kind;

/**
 * The PostgreSQL relation classes addressed by a qualified name in DDL.
 * @visibility public
 * @example Reading the SQL spelling of a relation class
 *     \SqlSemantics\Model\Definition\Catalog\Kind\RelationKind::MaterializedView->value // => 'MATERIALIZED VIEW'
 */
enum RelationKind: string
{
    case Table = 'TABLE';
    case Sequence = 'SEQUENCE';
    case View = 'VIEW';
    case MaterializedView = 'MATERIALIZED VIEW';
    case Index = 'INDEX';
    case ForeignTable = 'FOREIGN TABLE';
}
