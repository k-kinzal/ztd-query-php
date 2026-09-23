<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Storage;

/**
 * A named storage parameter whose value is a literal, an identifier, or implied by naming the option.
 *
 * @visibility public
 * @example Inspecting a declared storage parameter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE TABLE t(id integer) WITH (fillfactor = 70)');
 *     $statement->definition->table->properties->storageParameters[0]->name->parts // => ['fillfactor']
 */
final class Parameter
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly \SqlSemantics\Model\Relation\QualifiedName $name,
        public readonly \SqlSemantics\Model\Scalar\Value\Literal|\SqlSemantics\Model\Scalar\Value\ConfigurationIdentifier|\SqlSemantics\Model\Scalar\Value\ConfigurationKeyword|ImpliedSetting $value,
    ) {
    }
}
