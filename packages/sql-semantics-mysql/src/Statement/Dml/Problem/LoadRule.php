<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

/**
 * Mutually incompatible LOAD options, independent of files and target tables.
 *
 * The order of these refusals is observed on native MySQL 8.4.7 and 9.1.0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility public
 * @example Reading an incompatible algorithm choice
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule::XmlBulk->value // => 'Incorrect usage of LOAD XML and BULK Algorithm'
 */
enum LoadRule: string
{
    case XmlBulk = 'Incorrect usage of LOAD XML and BULK Algorithm';
    case ColumnsBulk = 'Incorrect usage of LOAD DATA with BULK Algorithm and column list specification';
    case AssignmentsBulk = 'Incorrect usage of LOAD DATA with BULK Algorithm and assignment to columns or variables';
    case LocalBulk = 'Incorrect usage of LOAD DATA with BULK Algorithm and LOCAL client file';
    case LinesStartingBulk = 'Incorrect usage of LOAD DATA with BULK Algorithm and LINES STARTING BY';
    case IgnoreBulk = 'Incorrect usage of LOAD DATA with BULK Algorithm and IGNORE clause';
    case MultipleFilesWithoutBulk = 'Incorrect usage of LOAD DATA without BULK Algorithm and multiple files';
    case UrlWithoutBulk = 'Incorrect usage of LOAD DATA without BULK Algorithm and URL source';
    case CompressionWithoutBulk = 'Incorrect usage of LOAD DATA without BULK Algorithm and COMPRESSION specified!';
}
