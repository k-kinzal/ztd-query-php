<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

/**
 * The two MySQL spellings that introduce inserted rows.
 *
 * @visibility public
 * @example Choosing the singular MySQL keyword
 *     \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesKeyword::Value->value // => 'VALUE'
 */
enum ValuesKeyword: string
{
    case Value = 'VALUE';
    case Values = 'VALUES';
}
