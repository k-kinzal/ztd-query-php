<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use LogicException;

/**
 * An implementation defect: valid syntax has no explicit semantic classification yet.
 * This is never converted into a semantic diagnostic or an opaque statement.
 * @visibility SqlSemantics
 */
final class UnclassifiedSql extends LogicException
{
}
