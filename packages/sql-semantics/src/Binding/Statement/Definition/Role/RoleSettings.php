<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Role;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\ResetBinder;
use SqlSemantics\Binding\Configuration\SettingBinder;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Statement\Configuration\ResetAllSettingsStatement;
use SqlSemantics\Model\Statement\Configuration\ResetSettingStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleResetAllStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleResetStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleSetStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds stored role settings by reusing the session SET and RESET readers.
 * @visibility SqlSemantics
 */
final class RoleSettings
{
    /**
     * Transaction characteristics are not stored settings and are diagnosed.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): AlterRoleSetStatement|AlterRoleResetStatement|AlterRoleResetAllStatement
    {
        $identifiers = $context->tables->identifiers;
        $spec = Tree::child($source, ['RoleSpec']);
        $role = $spec === null ? AllRoles::All : RoleSpecs::reference($spec, $identifiers);
        $qualifier = Tree::child($source, ['opt_in_database']);
        $database = $qualifier === null ? null : $identifiers->name((Tree::child($qualifier, ['name']) ?? throw new UnclassifiedSql('IN DATABASE requires its database name.'))->tokens()[0]);
        $clause = Tree::child($source, ['SetResetClause']) ?? throw new UnclassifiedSql('A role setting requires its SET or RESET clause.');
        $scope = new Scope($identifiers, queries: $context);
        $reset = Tree::child($clause, ['VariableResetStmt']);
        if ($reset !== null) {
            $statement = ResetBinder::bind($origin, $reset, $scope);
            return match (true) {
                $statement instanceof ResetAllSettingsStatement => new AlterRoleResetAllStatement($origin, $role, $database),
                $statement instanceof ResetSettingStatement => new AlterRoleResetStatement($origin, $role, $database, $statement->setting),
                default => throw new UnclassifiedSql('Unclassified role setting reset: ' . Tree::text($reset)),
            };
        }
        $rest = Tree::child($clause, ['set_rest']) ?? throw new UnclassifiedSql('A role setting requires its assignment.');
        $words = SettingTokens::words($rest->tokens());
        if ($words[0] === 'TRANSACTION' || ($words[0] === 'SESSION' && ($words[1] ?? '') === 'CHARACTERISTICS')) {
            throw new InvalidSql(InputViolation::RoleSetting, $rest);
        }
        if ($words === ['NAMES']) {
            return new AlterRoleSetStatement($origin, $role, $database, new DefaultSetting(['names'], SettingScope::Session, $clause));
        }
        $settings = (new SettingBinder())->bind($clause, $scope);
        $setting = $settings[0] ?? null;
        if (count($settings) !== 1 || !($setting instanceof AssignedSetting || $setting instanceof DefaultSetting || $setting instanceof CurrentSetting)) {
            throw new UnclassifiedSql('A role setting requires one parameter assignment: ' . Tree::text($rest));
        }
        return new AlterRoleSetStatement($origin, $role, $database, $setting);
    }
}
