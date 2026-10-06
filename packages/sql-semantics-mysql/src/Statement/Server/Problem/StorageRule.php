<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

/**
 * The rules of tablespace and log file group options whose violation the server reports while it parses them.
 *
 * Each case holds the server error it mirrors.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Reading the server error of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule::RepeatedOption->value // => 'ER_FILEGROUP_OPTION_ONLY_ONCE'
 */
enum StorageRule: string
{
    case RepeatedOption = 'ER_FILEGROUP_OPTION_ONLY_ONCE';
    case WrongSize = 'ER_WRONG_SIZE_NUMBER';
    case SizeOverflow = 'ER_SIZE_OVERFLOW_ERROR';
}
