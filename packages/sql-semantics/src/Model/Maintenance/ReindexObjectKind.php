<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance;

/**
 * The object whose indexes are rebuilt.
 * @visibility public
 * @example Choosing the reindex target
 *     \SqlSemantics\Model\Maintenance\ReindexObjectKind::Index->value // => 'INDEX'
 */
enum ReindexObjectKind: string
{
    case Index = 'INDEX';
    case Table = 'TABLE';
    case Schema = 'SCHEMA';
}
