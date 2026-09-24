<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Construction;

/**
 * What a SQL/JSON constructor does with a NULL value: write a JSON null, or leave the member or element out.
 * JSON_OBJECT and JSON_OBJECTAGG default to NULL ON NULL; JSON_ARRAY and JSON_ARRAYAGG default to ABSENT ON NULL.
 * @visibility public
 * @example Reading the clause spelling
 *     \SqlSemantics\Model\Scalar\Document\Construction\JsonNullHandling::Absent->value // => 'ABSENT ON NULL'
 */
enum JsonNullHandling: string
{
    case Null = 'NULL ON NULL';
    case Absent = 'ABSENT ON NULL';
}
