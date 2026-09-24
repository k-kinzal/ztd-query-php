<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Statement\Configuration\System as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds ALTER SYSTEM with the session SET and RESET readers.
 * @visibility SqlSemantics
 */
final class SystemSettings
{
    /**
     * Returns the persisted assignment or removal of an AlterSystemStmt.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): Statement\AlterSystemSetStatement|Statement\AlterSystemResetStatement|Statement\AlterSystemResetAllStatement
    {
        $scope = new Scope($context->tables->identifiers, queries: $context);
        $tokens = array_slice($source->tokens(), 2);
        if (strtoupper($tokens[0]->text ?? '') === 'RESET') {
            $setting = StoredSettings::reset($origin, new Node('VariableResetStmt', 0, $tokens), $scope);
            return $setting === null ? new Statement\AlterSystemResetAllStatement($origin) : new Statement\AlterSystemResetStatement($origin, $setting);
        }
        $setting = StoredSettings::assignment(new Node('SetResetClause', 0, $tokens), $scope);
        if ($setting instanceof CurrentSetting) {
            throw new UnclassifiedSql('ALTER SYSTEM has no FROM CURRENT form.');
        }
        return new Statement\AlterSystemSetStatement($origin, $setting);
    }
}
