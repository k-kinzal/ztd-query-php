<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration\Transaction;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Model\Statement\Configuration\Transaction as Statement;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Transaction\Access;
use SqlSemantics\Model\Transaction\Configuration\DefaultScope;
use SqlSemantics\Model\Transaction\Isolation;

/**
 * Binds MySQL isolation and access requests with their next-transaction or default scope.
 * @visibility SqlSemantics
 */
final class MySqlSettings
{
    /**
     * Reads the MySQL one-shot or explicitly scoped configuration form.
     */
    public static function bind(Origin $origin, Node $source): ?ConfigurationStatement
    {
        $characteristics = Tree::outer($source, ['transaction_characteristics'])[0] ?? null;
        if ($characteristics === null) {
            return null;
        }
        $isolation = array_values(array_filter(Tree::outer($characteristics, ['isolation_level']), Tree::hasTokens(...)))[0] ?? null;
        $access = array_values(array_filter(Tree::outer($characteristics, ['transaction_access_mode']), Tree::hasTokens(...)))[0] ?? null;
        $isolation = $isolation === null ? null : Isolation::from(implode(' ', array_slice(SettingTokens::words($isolation->tokens()), 2)));
        $access = $access === null ? null : Access::from(strtoupper(Tree::text($access)));
        $scope = Tree::outer($source, ['option_type'])[0] ?? null;
        if ($scope === null) {
            return new Statement\SetNextTransactionStatement($origin, $isolation, $access);
        }
        $scope = strtoupper(Tree::text($scope));
        return new Statement\SetDefaultTransactionStatement($origin, $scope === 'LOCAL' ? DefaultScope::Session : DefaultScope::from($scope), $isolation, $access);
    }
}
