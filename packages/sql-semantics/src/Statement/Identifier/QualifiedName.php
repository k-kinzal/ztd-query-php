<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Identifier;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * A decoded object name with its optional schema and catalog qualifiers.
 *
 * @visibility public
 * @example Reading the parts of a qualified name
 *     $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users'), new \SqlSemantics\Statement\Identifier\Name('app'));
 *     [$name->schema?->value, $name->name->value] // => ['app', 'users']
 */
final class QualifiedName
{
    use Snapshot;

    /**
     * @param Name $name The object name
     * @param Name|null $schema The schema (MySQL: database) qualifier
     * @param Name|null $catalog The catalog qualifier, which requires a schema
     */
    public function __construct(
        public readonly Name $name,
        public readonly ?Name $schema = null,
        public readonly ?Name $catalog = null,
    ) {
        Check::input($catalog === null || $schema !== null, 'A catalog qualifier requires a schema qualifier.');
    }
}
