<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet;
use SqlSemantics\Model\Configuration\Connection\ConnectionNames;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Configuration\Setting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\ConfigurationIdentifier;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Applies MySQL's SET rules for system variables: a scope keyword carries over to later unscoped items, names have at most one prefix, and a bare identifier value is a name.
 * @visibility SqlSemantics
 */
final class SettingScopes
{
    /**
     * Gives unscoped items the most recent GLOBAL, PERSIST, PERSIST_ONLY or SESSION keyword of the statement, as the server does.
     * @param list<Token> $group One SET item
     * @param list<Setting|ConnectionNames|ConnectionCharacterSet> $settings The item's bound settings
     * @return array{list<Setting|ConnectionNames|ConnectionCharacterSet>, SettingScope|null}
     */
    public static function carry(array $group, array $settings, ?SettingScope $carried): array
    {
        $words = SettingTokens::words($group);
        if (in_array($words[0] ?? '', ['SESSION', 'LOCAL', 'GLOBAL', 'PERSIST', 'PERSIST_ONLY'], true) && !in_array($words[1] ?? '', ['.', '=', ':='], true)) {
            $first = $settings[0] ?? null;
            return [$settings, $first instanceof Setting ? $first->scope : $carried];
        }
        if ($carried === null || ($words[0] ?? '') === '@') {
            return [$settings, $carried];
        }
        $scoped = array_map(static fn (Setting|ConnectionNames|ConnectionCharacterSet $setting): Setting|ConnectionNames|ConnectionCharacterSet => match (true) {
            $setting instanceof AssignedSetting && $setting->scope === SettingScope::Session => new AssignedSetting($setting->name, $carried, $setting->source, $setting->values),
            $setting instanceof DefaultSetting && $setting->scope === SettingScope::Session => new DefaultSetting($setting->name, $carried, $setting->source),
            default => $setting,
        }, $settings);
        return [$scoped, $carried];
    }

    /**
     * Rejects a prefix that repeats a scope keyword, as in GLOBAL.GLOBAL.name, and names with more than one prefix.
     * @param list<string> $name
     * @throws InvalidSql
     */
    public static function name(array $name, Node $source): void
    {
        if (count($name) > 2 || (count($name) === 2 && in_array(strtoupper($name[0]), ['GLOBAL', 'LOCAL', 'SESSION'], true))) {
            throw new InvalidSql(InputViolation::SessionSetting, $source);
        }
    }

    /**
     * Binds a value spelled as one identifier as the name it denotes, whether or not it is quoted.
     * @param list<Token> $tokens One assigned value
     */
    public static function identifier(array $tokens, Node $source, Scope $scope): ?ConfigurationIdentifier
    {
        $token = $tokens[0] ?? null;
        if ($token === null || count($tokens) !== 1) {
            return null;
        }
        foreach (Tree::outer($source, ['simple_ident']) as $identifier) {
            if ($identifier->span() === [$token->offset, $token->end()]) {
                $facts = new ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'text'), Nullability::NotNull);
                return new ConfigurationIdentifier($facts, new Node('configuration_value', 0, $tokens), [$scope->identifiers->name($token)]);
            }
        }
        return null;
    }
}
