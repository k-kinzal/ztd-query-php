<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An ordered configuration effect, including scope, qualified name, and value list.
 *
 * @example Reading structured effects
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SET LOCAL work_mem TO DEFAULT');
 *     $statement->settings[0]->scope // => 'local'
 *
 * @visibility public
 */
final class Setting
{
    /**
     * @param list<string> $name Qualified setting or variable name; * denotes all settings
     * @param string $scope SQL scope, such as session, local, global, user, or next-transaction
     * @param string $action set, reset, read, or from-current
     * @param list<Expression> $values Values in SQL order; DEFAULT is an explicit expression kind
     * @param Node $source Setting syntax
     * @param bool $ifExists Whether an absent persisted variable is tolerated
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly array $name,
        public readonly string $scope,
        public readonly string $action,
        public readonly array $values,
        public readonly Node $source,
        public readonly bool $ifExists = false,
    ) {
        \SqlSemantics\Model\Validation\Collections::strings($name);
        \SqlSemantics\Model\Validation\Collections::objects($values, Expression::class);
        if ($name === [] || $scope === '' || !in_array($action, ['set', 'reset', 'read', 'from-current'], true)) {
            throw new InvalidStructure('A setting requires a name, scope, and valid action.');
        }
        if ($action !== 'set' && $values !== []) {
            throw new InvalidStructure('Only a setting assignment carries values.');
        }
    }
}
