<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Column;

/**
 * The TOAST storage strategies a column can select; DEFAULT restores the type's strategy.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\Column\ColumnStorageMode::External->value // => 'EXTERNAL'
 */
enum ColumnStorageMode: string
{
    case Plain = 'PLAIN';
    case External = 'EXTERNAL';
    case Extended = 'EXTENDED';
    case Main = 'MAIN';
    case Default = 'DEFAULT';
}
