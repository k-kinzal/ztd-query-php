<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

/**
 * Configuration operations; each concrete form has its required setting operands.
 * @visibility public
 */
abstract class ConfigurationStatement extends \SqlSemantics\Model\BoundStatement
{
    /**
     * @return list<\SqlSemantics\Model\Configuration\DefaultSetting|\SqlSemantics\Model\Configuration\AssignedUserVariable|\SqlSemantics\Model\Configuration\AssignedSetting>
     */
    public function assignments(): array
    {
        return [];
    }
}
