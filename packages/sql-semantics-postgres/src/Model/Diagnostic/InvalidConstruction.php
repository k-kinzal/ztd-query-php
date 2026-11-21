<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Diagnostic;

use InvalidArgumentException;

/**
 * A construction input outside the documented domain of a constructor.
 *
 * Examples are a value of a foreign class, a node used at two positions of one
 * statement, or a declaration of another language profile. A semantic problem
 * of the requested SQL, such as a missing column, is never reported this way.
 *
 * @visibility public
 * @example Rejecting a qualified name with a catalog but no schema
 *     new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t'), null, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('c')) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class InvalidConstruction extends InvalidArgumentException implements \SqlSemantics\AnalysisException
{
}
