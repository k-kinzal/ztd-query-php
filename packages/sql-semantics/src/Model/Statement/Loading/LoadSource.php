<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

/**
 * Where a load finds its input: a file, an object storage URL, or an S3 location.
 * @visibility public
 * @example Reading the source kind of a load
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     (new \SqlSemantics\Binder($schema))->bind("LOAD DATA FROM URL 'https://example.com/t.csv' INTO TABLE t ALGORITHM = BULK")->location // => \SqlSemantics\Model\Statement\Loading\LoadSource::Url
 */
enum LoadSource: string
{
    case File = 'INFILE';
    case Url = 'URL';
    case S3 = 'S3';
}
