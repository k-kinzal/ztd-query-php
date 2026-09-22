<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Json\Response;

/**
 * A classified SQL/JSON behavior policy.
 * @visibility public
 */
enum ValueBehavior: string implements ValueResponse
{
    case Default = '';
    case Error = 'ERROR';
    case Null = 'NULL';
    case EmptyArray = 'EMPTY ARRAY';
    case EmptyObject = 'EMPTY OBJECT';
}
