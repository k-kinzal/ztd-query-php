<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration\Password;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Configuration\SettingBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Configuration;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds variable clauses embedded in MySQL 5.6 account SET lists.
 * @visibility SqlSemantics
 */
final class LegacyAssignments
{
    /**
     * @param list<\SqlParser\Lexer\Token> $tokens
     * @return list<SetStatement>
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, array $tokens, Node $source, Scope $scope): array
    {
        $operations = [];
        foreach ((new SettingBinder())->setting($tokens, $source, $scope, 'SET') as $setting) {
            if (!$setting instanceof Configuration\DefaultSetting && !$setting instanceof Configuration\AssignedUserVariable && !$setting instanceof Configuration\AssignedSetting && !$setting instanceof Configuration\CurrentSetting && !$setting instanceof Configuration\Connection\ConnectionNames && !$setting instanceof Configuration\Connection\ConnectionCharacterSet) {
                throw new UnclassifiedSql('Unclassified mixed SET effect.');
            }
            $operations[] = new SetStatement($origin, [$setting]);
        }
        return $operations;
    }
}
