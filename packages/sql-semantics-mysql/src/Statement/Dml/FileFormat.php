<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Statement\Node;

/**
 * The text file format of INTO OUTFILE and LOAD DATA: character set, field and line options.
 *
 * The data manipulation family provides the structure; SELECT ... INTO OUTFILE
 * holds it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 */
interface FileFormat extends Node
{
}
