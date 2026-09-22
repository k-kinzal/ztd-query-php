<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Storage;

use Override;

/**
 * A nested storage location with a mandatory writable base.
 *
 * @visibility public
  * @example Inspecting ElementPath
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(xmlnamespaces INTEGER[])'));
 *     $statement = $binder->bind('UPDATE t SET xmlnamespaces[1]=2');
 *     $statement->writes[0]->target instanceof \SqlSemantics\Model\Write\Storage\ElementPath // => true
 */
final class ElementPath implements Path
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly Path $base, public readonly \SqlSemantics\Model\Expression $index)
    {
        if ($index->type->dialect !== $base->type()->dialect) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A storage path and its index must use the same dialect.');
        }
    }

    /**
     * Returns the column owning this storage location.
     */
    #[Override]
    public function column(): \SqlSemantics\Model\Scalar\Reference\ColumnReference|\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference
    {
        return $this->base->column();
    }

    /**
     * Returns the declared destination type.
     */
    #[Override]
    public function type(): \SqlSemantics\Type\TypeDescriptor
    {
        return $this->base->type()->identity instanceof \SqlSemantics\Type\Identity\ArrayStorage ? $this->base->type()->identity->element : \SqlSemantics\Type\TypeDescriptor::builtin($this->base->type()->dialect, 'unknown');
    }
}
