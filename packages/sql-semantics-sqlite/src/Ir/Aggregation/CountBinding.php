<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;

/**
 * Binds the native builtin aggregate and its position in the grouped input.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CountBinding
{
    /**
     * Resolves the builtin operator and its written argument role.
     */
    public function lower(FunctionCall $call, GroupBinding $binding): Count
    {
        Check::input(
            strtolower($call->name->value) === 'count' && $call->filter === null && $call->over === null && $call->order === []
                && ($call->star || count($call->arguments) === 1),
            'A grouped aggregate requires builtin COUNT without a window, filter or ordering.',
        );
        if ($call->star) {
            return new Count();
        }
        $distinct = $call->quantifier === SetQuantifier::Distinct;
        $key = $binding->key($call->arguments[0], $distinct);

        return new Count($key->column, $distinct ? $key->collation : null);
    }
}
