<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

/**
 * Whether a MySQL load reads delimited text rows or XML rows.
 * @visibility public
 * @example Reading the file format of a load
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     (new \SqlSemantics\Binder($schema))->bind("LOAD XML INFILE 'rows.xml' INTO TABLE t")->format // => \SqlSemantics\Model\Statement\Loading\LoadFormat::Xml
 */
enum LoadFormat: string
{
    case Data = 'DATA';
    case Xml = 'XML';
}
