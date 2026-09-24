<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

/**
 * The compression methods a column can select; DEFAULT follows the server setting.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\Column\ColumnCompression::Lz4->value // => 'lz4'
 */
enum ColumnCompression: string
{
    case Pglz = 'pglz';
    case Lz4 = 'lz4';
    case Default = 'default';
}
