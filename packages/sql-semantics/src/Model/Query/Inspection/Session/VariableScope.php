<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Session;

/**
 * Which values SHOW VARIABLES and SHOW STATUS report; LOCAL and an omitted scope both select the session values.
 * @visibility public
 * @example Reading the requested scope
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW LOCAL VARIABLES');
 *     $statement->scope // => \SqlSemantics\Model\Query\Inspection\Session\VariableScope::Session
 */
enum VariableScope: string
{
    case Session = 'SESSION';
    case Global = 'GLOBAL';
}
