<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\ResetBinder;
use SqlSemantics\Binding\Configuration\SettingBinder;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Configuration\ResetSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Configuration\ResetAllSettingsStatement;
use SqlSemantics\Model\Statement\Configuration\ResetSettingStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads stored configuration defaults with the session SET and RESET readers.
 * @visibility SqlSemantics
 */
final class StoredSettings
{
    /**
     * Reads the assignment of a SET clause; session-only commands such as transaction characteristics are diagnosed.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function assignment(Node $clause, Scope $scope): AssignedSetting|DefaultSetting|CurrentSetting
    {
        $words = SettingTokens::words(array_slice($clause->tokens(), 1));
        if (in_array($words[0] ?? '', ['TRANSACTION', 'CATALOG'], true) || (($words[0] ?? '') === 'SESSION' && ($words[1] ?? '') === 'CHARACTERISTICS')) {
            throw new InvalidSql(InputViolation::StoredSetting, $clause);
        }
        if ($words === ['NAMES']) {
            return new DefaultSetting(['client_encoding'], SettingScope::Session, $clause);
        }
        $settings = (new SettingBinder())->bind($clause, $scope);
        $setting = $settings[0] ?? null;
        if (count($settings) !== 1 || !($setting instanceof AssignedSetting || $setting instanceof DefaultSetting || $setting instanceof CurrentSetting) || $setting->scope !== SettingScope::Session) {
            throw new UnclassifiedSql('A stored setting requires one parameter assignment: ' . Tree::text($clause));
        }
        return $setting;
    }

    /**
     * Reads a RESET clause; null means every stored default.
     * @throws UnclassifiedSql
     */
    public static function reset(Origin $origin, Node $clause, Scope $scope): ?ResetSetting
    {
        $statement = ResetBinder::bind($origin, $clause, $scope);
        return match (true) {
            $statement instanceof ResetAllSettingsStatement => null,
            $statement instanceof ResetSettingStatement => $statement->setting,
            default => throw new UnclassifiedSql('Unclassified stored setting reset: ' . Tree::text($clause)),
        };
    }
}
