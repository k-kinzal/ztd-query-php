<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

/**
 * A completed grammar boundary that stops reading after an earlier condition.
 *
 * A stored-program statement is prepared when its production completes. An
 * earlier grammar error prevents reading the next statement, without adding
 * another error or warning. Observed on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility public
 * @example Identifying the boundary after a refused program statement
 *     \SqlSemantics\Platform\MySql\Statement\Notice\ParseStop::ProgramStatement->name // => 'ProgramStatement'
 */
enum ParseStop
{
    case ProgramStatement;
}
