<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

/**
 * The properties LIKE can copy from the source table.
 *
 * Mirrors the `CREATE_TABLE_LIKE_*` bits. Column names, types and NOT NULL
 * constraints are always copied.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading a LIKE option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (LIKE u INCLUDING ALL EXCLUDING INDEXES)');
 *     $create->statement->definition->elements[0]->options[1]->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOptionKind::Indexes
 */
enum LikeOptionKind: string
{
    case Comments = 'COMMENTS';
    case Compression = 'COMPRESSION';
    case Constraints = 'CONSTRAINTS';
    case Defaults = 'DEFAULTS';
    case Identity = 'IDENTITY';
    case Generated = 'GENERATED';
    case Indexes = 'INDEXES';
    case Statistics = 'STATISTICS';
    case Storage = 'STORAGE';
    case All = 'ALL';
}
