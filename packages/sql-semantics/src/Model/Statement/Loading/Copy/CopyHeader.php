<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

/**
 * The header line of COPY: absent, present, or, when reading, present and required to match the column names.
 * @visibility public
 * @example Reading a matched header
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COPY t FROM STDIN (FORMAT csv, HEADER match)", strict: false);
 *     $statement->options->header === \SqlSemantics\Model\Statement\Loading\Copy\CopyHeader::Match // => true
 */
enum CopyHeader: string
{
    case Absent = 'false';
    case Present = 'true';
    case Match = 'match';
}
