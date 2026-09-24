<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

/**
 * The identity of one PostgreSQL catalog object as a command names it, without resolving it.
 * Each implementation carries exactly the operands its object class needs.
 * @visibility public
 * @example Addressing a schema
 *     $object = new \SqlSemantics\Model\Definition\Catalog\NamedIdentity(\SqlSemantics\Model\Definition\Catalog\Kind\NamedObjectKind::Schema, 'app');
 *     $object instanceof \SqlSemantics\Model\Definition\ObjectAddress // => true
 */
interface ObjectAddress
{
}
