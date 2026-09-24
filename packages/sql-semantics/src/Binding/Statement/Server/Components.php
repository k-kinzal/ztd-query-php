<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Administration as Statement;

/**
 * Binds INSTALL COMPONENT and UNINSTALL COMPONENT; the plugin forms belong to the session binder.
 * @visibility SqlSemantics
 */
final class Components
{
    /**
     * Returns null for the plugin forms.
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): ?BoundStatement
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), array_slice($node->tokens(), 0, 2));
        if (($words[1] ?? '') !== 'COMPONENT') {
            return null;
        }
        $components = array_map(Literals::text(...), Tree::outer($node, ['TEXT_STRING_sys']));
        if ($words[0] === 'UNINSTALL') {
            return new Statement\UninstallComponentStatement($origin, $components);
        }
        $scope = new Scope($context->tables->identifiers, queries: $context);
        return new Statement\InstallComponentStatement($origin, $components, array_map(static fn (Node $assignment): AssignedSetting => self::setting($assignment, $scope), Tree::outer($node, ['install_set_value'])));
    }

    /**
     * Binds one variable assignment; an omitted scope means GLOBAL.
     * @throws UnclassifiedSql
     */
    public static function setting(Node $assignment, Scope $scope): AssignedSetting
    {
        $type = Tree::child($assignment, ['install_option_type']);
        $variable = Tree::child($assignment, ['lvalue_variable']) ?? throw new UnclassifiedSql('A component variable assignment requires a variable name.');
        $value = Tree::child($assignment, ['install_set_rvalue']) ?? throw new UnclassifiedSql('A component variable assignment requires a value.');
        $tokens = $value->tokens();
        if ($tokens === []) {
            throw new UnclassifiedSql('A component variable assignment requires a value.');
        }
        $name = [];
        foreach ($variable->tokens() as $token) {
            if ($token->text !== '.') {
                $name[] = $scope->identifiers->name($token);
            }
        }
        $persist = $type !== null && strtoupper(Tree::text($type)) === 'PERSIST';
        return new AssignedSetting($name, $persist ? SettingScope::Persist : SettingScope::Global, $assignment, [SettingTokens::value($tokens, $value, $scope)]);
    }
}
