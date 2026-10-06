<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

/**
 * Where LOAD reads from: a file (INFILE), a URL or an S3 location (the last two in HeatWave releases of 8.x and 9.x grammars).
 *
 * Each case holds the keyword it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html, https://dev.mysql.com/doc/heatwave-aws/en/mys-hw-aws-bulk-ingest.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile->value // => 'INFILE'
 */
enum LoadSource: string
{
    case Infile = 'INFILE';
    case Url = 'URL';
    case S3 = 'S3';
}
