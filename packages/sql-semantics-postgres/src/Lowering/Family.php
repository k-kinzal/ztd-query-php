<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering;

/**
 * The families that lower statements; each owns a set of statement nonterminals and their parse nodes.
 *
 * @visibility SqlSemantics
 */
enum Family
{
    case Query;
    case Manipulation;
    case Table;
    case Catalog;
    case Routine;
    case Access;
    case Utility;
}
