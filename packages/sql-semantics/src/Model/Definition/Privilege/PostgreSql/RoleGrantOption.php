<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql;

/**
 * A membership option value; ADMIN OPTION and ADMIN TRUE are the same request.
 * @visibility public
 * @example Reading a disabled option
 *     $option = new \SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantOption(\SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute::Set, false);
 *     $option->granted // => false
 */
final class RoleGrantOption
{
    /**
     * Retains the option and its boolean value.
     */
    public function __construct(public readonly RoleGrantAttribute $attribute, public readonly bool $granted)
    {
    }
}
