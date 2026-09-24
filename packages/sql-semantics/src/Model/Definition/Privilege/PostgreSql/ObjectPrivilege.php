<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql;

/**
 * One privilege requested on whole objects.
 * @visibility public
 * @example Reading the privilege
 *     (new \SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege(\SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege::Usage))->privilege->value // => 'USAGE'
 */
final class ObjectPrivilege
{
    /**
     * Retains the privilege without deciding whether the object class accepts it.
     */
    public function __construct(public readonly Privilege $privilege)
    {
    }
}
