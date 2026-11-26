<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page;

/**
 * SQLite's native pager size and count operations.
 *
 * @visibility public
 * @example Selecting the current database page count
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragmaKind::Count->value // => 'page_count'
 */
enum PagePragmaKind: string
{
    case Size = 'page_size';
    case Count = 'page_count';
    case MaximumCount = 'max_page_count';
}
