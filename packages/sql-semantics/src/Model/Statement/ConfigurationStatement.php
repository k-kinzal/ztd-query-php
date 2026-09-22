<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

/**
 * Configuration operations; each concrete form has its required setting operands.
 * @visibility public
  * @example Inspecting ConfigurationStatement
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SET search_path=public');
 *     $statement instanceof \SqlSemantics\Model\Statement\ConfigurationStatement // => true
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
