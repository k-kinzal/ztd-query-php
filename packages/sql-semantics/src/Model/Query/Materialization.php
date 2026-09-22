<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

/**

 * @visibility public

 */
enum Materialization: string
{
    case Default = 'default';
    case Materialized = 'materialized';
    case Inline = 'not-materialized';
}
