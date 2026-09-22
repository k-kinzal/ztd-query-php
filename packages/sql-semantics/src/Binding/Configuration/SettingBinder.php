<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

use LogicException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Setting;

/**
 * Lowers SET, RESET, and PRAGMA into ordered named configuration effects.
 *
 * @visibility SqlSemantics
 */
final class SettingBinder
{
    /**
     * @return list<Setting>
     */
    public function bind(Node $source, Scope $scope): array
    {
        $tokens = array_values(array_filter($source->tokens(), static fn (Token $token): bool => $token->text !== '' && $token->text !== ';'));
        $verb = strtoupper($tokens[0]->text ?? '');
        if (!in_array($verb, ['SET', 'PRAGMA'], true)) {
            return [];
        }
        array_shift($tokens);
        if ($verb === 'PRAGMA') {
            return [$this->pragma($tokens, $source, $scope)];
        }
        $words = SettingTokens::words($tokens);
        if (in_array('TRANSACTION', array_slice($words, 0, 5), true)) {
            return (new TransactionSettings())->bind($tokens, $source, $scope);
        }
        $groups = $scope->identifiers->dialect === Dialect::MySql ? SettingTokens::split($tokens) : [$tokens];
        $result = [];
        foreach ($groups as $group) {
            array_push($result, ...$this->setting($group, $source, $scope, $verb));
        }
        return $result;
    }

    /**
     * @param list<Token> $tokens
     * @return list<Setting>
     */
    public function setting(array $tokens, Node $source, Scope $scope, string $verb): array
    {
        [$tokens, $settingScope] = $this->scope($tokens, $scope->identifiers->dialect);
        $words = SettingTokens::words($tokens);
        $delimiter = null;
        foreach ($words as $index => $word) {
            if (in_array($word, ['=', ':=', 'TO', 'FROM'], true)) {
                $delimiter = $index;
                break;
            }
        }
        if ($delimiter !== null) {
            $name = $scope->identifiers->parts(new Node('setting_name', 0, array_slice($tokens, 0, $delimiter)));
            $action = $words[$delimiter] === 'FROM' ? 'from-current' : 'set';
            return [$this->make($name, $settingScope, $action, array_slice($tokens, $delimiter + 1), $source, $scope)];
        }
        return (new SpecialSettings())->bind($tokens, $settingScope, $source, $scope);
    }

    /**
     * Separates explicit variable scope from the qualified configuration name.
     *
     * @param list<Token> $tokens
     * @return array{list<Token>, string}
     */
    public function scope(array $tokens, Dialect $dialect): array
    {
        $settingScope = 'session';
        $words = SettingTokens::words($tokens);
        if (in_array($words[0] ?? '', ['SESSION', 'LOCAL', 'GLOBAL', 'PERSIST', 'PERSIST_ONLY'], true) && !in_array($words[1] ?? '', ['AUTHORIZATION', '.', '=', ':=', 'TO'], true)) {
            $settingScope = strtolower(str_replace('_', '-', array_shift($words)));
            array_shift($tokens);
        }
        if (($words[0] ?? '') === '@') {
            array_shift($tokens);
            $settingScope = 'user';
            if (($tokens[0]->text ?? '') === '@') {
                array_shift($tokens);
                $settingScope = 'session';
                if (($tokens[1]->text ?? '') === '.' && in_array(strtoupper($tokens[0]->text), ['SESSION', 'LOCAL', 'GLOBAL'], true)) {
                    $settingScope = strtolower($tokens[0]->text);
                    $tokens = array_slice($tokens, 2);
                }
            }
        }
        if ($dialect === Dialect::MySql && $settingScope === 'local') {
            $settingScope = 'session';
        }
        return [$tokens, $settingScope];
    }

    /**
     * @param list<string> $name
     * @param list<Token> $tokens
     * @throws LogicException
     */
    public function make(array $name, string $settingScope, string $action, array $tokens, Node $source, Scope $scope): Setting
    {
        if ($name === []) {
            Tree::invalid($source, 'setting name');
        }
        if ($action === 'set' && count($tokens) === 1 && in_array($tokens[0]->name, ['DEFAULT', 'DEFAULT_SYM'], true)) {
            return new \SqlSemantics\Model\Configuration\DefaultSetting($name, \SqlSemantics\Model\Configuration\SettingScope::from($settingScope), $source);
        }
        $values = [];
        if ($action === 'set') {
            foreach (SettingTokens::split($tokens) as $value) {
                if ($value !== []) {
                    $values[] = SettingTokens::value($value, $source, $scope);
                }
            }
        }
        if ($settingScope === 'user' && $action === 'set') {
            if (count($values) !== 1) {
                throw new \SqlSemantics\Binding\Statement\UnclassifiedSql('A user variable requires one assignment expression.');
            }
            return UserVariableAssignment::bind(implode('.', $name), $values[0], $source, $scope);
        }
        $lifetime = \SqlSemantics\Model\Configuration\SettingScope::from($settingScope);
        return match ($action) {
            'set' => new \SqlSemantics\Model\Configuration\AssignedSetting($name, $lifetime, $source, $values),
            'reset' => new \SqlSemantics\Model\Configuration\ResetSetting($name, $lifetime, $source),
            'read' => new \SqlSemantics\Model\Configuration\ReadSetting($name, $lifetime, $source),
            'from-current' => new \SqlSemantics\Model\Configuration\CurrentSetting($name, $lifetime, $source),
            default => throw new LogicException('Unclassified setting effect: ' . $action),
        };
    }

    /**
     * @param list<Token> $tokens
     */
    public function pragma(array $tokens, Node $source, Scope $scope): Setting
    {
        $nameTokens = [];
        foreach ($tokens as $token) {
            if (in_array($token->text, ['=', '('], true)) {
                break;
            }
            $nameTokens[] = $token;
        }
        $values = array_slice($tokens, count($nameTokens) + 1);
        if (($values[count($values) - 1]->text ?? '') === ')') {
            array_pop($values);
        }
        return $this->make($scope->identifiers->parts(new Node('pragma_name', 0, $nameTokens)), 'database', count($nameTokens) === count($tokens) ? 'read' : 'set', $values, $source, $scope);
    }
}
