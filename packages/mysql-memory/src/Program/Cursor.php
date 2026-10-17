<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration;

/**
 * A cursor in scope: its query, and while it is open the rows the query answered and the next row to fetch.
 *
 * OPEN runs the query and keeps its rows; FETCH reads them in order; CLOSE forgets them, as
 * the end of the block that declares the cursor does.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cursors.html.
 *
 * @visibility MySqlMemory
 */
final class Cursor
{
    /**
     * @var list<list<int|float|string|null>>|null The rows of the query, or null while the cursor is closed
     */
    public ?array $rows = null;

    /**
     * @var list<Domain> The types of the columns of the query
     */
    public array $domains = [];

    /**
     * The position of the next row to fetch.
     */
    public int $position = 0;

    /**
     * @param CursorDeclaration $declaration The declaration
     */
    public function __construct(public readonly CursorDeclaration $declaration)
    {
    }
}
