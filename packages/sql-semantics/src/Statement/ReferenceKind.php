<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * What a table name in a statement resolves to.
 *
 * @visibility public
 * @example Telling a table from a common table expression
 *     \SqlSemantics\Statement\ReferenceKind::CommonTableExpression->name // => 'CommonTableExpression'
 */
enum ReferenceKind
{
    /**
     * A table declared by one of the statement's dependencies.
     */
    case Dependency;

    /**
     * A table the statement itself declares.
     */
    case Declaration;

    /**
     * A common table expression the statement itself defines.
     */
    case CommonTableExpression;

    /**
     * A table the statement drops.
     */
    case Drop;
}
