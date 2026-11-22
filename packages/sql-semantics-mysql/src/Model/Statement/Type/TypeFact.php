<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Type;

/**
 * What an operation knows about the type of an expression or output slot.
 *
 * The closed alternatives are a known type, a choice of known types, the type
 * of a bare NULL, a dependence on missing inputs, and an invalid request.
 *
 * @visibility SqlSemantics
 */
interface TypeFact
{
}
