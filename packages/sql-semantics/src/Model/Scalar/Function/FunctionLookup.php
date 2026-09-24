<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

/**
 * How MySQL finds an unresolved function: through a function form of the grammar, such as `LEFT(...)`,
 * or by name among native, loadable and stored functions, as a call written with an identifier such as `` `left`(...) `` is.
 * @visibility public
 * @example Reading how a quoted function name is found
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT `name`()');
 *     $statement->outputs[0]->expression->function->lookup // => \SqlSemantics\Model\Scalar\Function\FunctionLookup::Name
 */
enum FunctionLookup: string
{
    case Grammar = 'grammar';
    case Name = 'name';
}
