<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ResetSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Configuration as Statement;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Separates named reset operations from all-parameter reset operations.
 * @visibility SqlSemantics
 */
final class ResetBinder
{
    /**
     * Binds RESET without representing ALL as a fabricated parameter name.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $node, Scope $scope): ConfigurationStatement
    {
        $tokens = $node->tokens();
        $words = SettingTokens::words($tokens);
        if ($origin->dialect === Dialect::PostgreSql) {
            if ($words === ['RESET', 'ALL']) {
                return new Statement\ResetAllSettingsStatement($origin);
            }
            $name = match (implode(' ', array_slice($words, 1))) {
                'TIME ZONE' => ['timezone'],
                'TRANSACTION ISOLATION LEVEL' => ['transaction_isolation'],
                'SESSION AUTHORIZATION' => ['session_authorization'],
                default => $scope->identifiers->parts(new Node('setting_name', 0, array_slice($tokens, 1))),
            };
            return new Statement\ResetSettingStatement($origin, new ResetSetting($name, SettingScope::Session, $node));
        }
        if ($origin->dialect === Dialect::MySql && ($words[1] ?? '') === 'PERSIST') {
            if (count($tokens) === 2) {
                return new Statement\ResetAllPersistedVariablesStatement($origin);
            }
            $ifExists = ($words[2] ?? '') === 'IF';
            $name = Tree::outer($node, ['persisted_variable_ident'])[0] ?? throw new UnclassifiedSql('RESET PERSIST requires a named variable.');
            return new Statement\ResetSettingStatement($origin, new ResetSetting($scope->identifiers->parts($name), SettingScope::Persist, $node, $ifExists));
        }
        throw new UnclassifiedSql('Unclassified RESET operation: ' . $node->toString());
    }
}
