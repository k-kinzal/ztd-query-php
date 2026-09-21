<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration;

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
        if (!in_array($verb, ['SET', 'RESET', 'PRAGMA'], true)) {
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
        if ($verb === 'RESET') {
            $ifExists = array_slice($words, 0, 2) === ['IF', 'EXISTS'];
            $tokens = $ifExists ? array_slice($tokens, 2) : $tokens;
            $name = $tokens === [] || ($words[0] ?? '') === 'ALL' ? ['*'] : $scope->identifiers->parts(new Node('setting_name', 0, $tokens));
            return [new Setting($name, $settingScope, 'reset', [], $source, $ifExists)];
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
        if (in_array($words[0] ?? '', ['SESSION', 'LOCAL', 'GLOBAL', 'PERSIST', 'PERSIST_ONLY'], true) && ($words[1] ?? '') !== 'AUTHORIZATION') {
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
     */
    public function make(array $name, string $settingScope, string $action, array $tokens, Node $source, Scope $scope): Setting
    {
        if ($name === []) {
            Tree::invalid($source, 'setting name');
        }
        $values = [];
        if ($action === 'set') {
            foreach (SettingTokens::split($tokens) as $value) {
                if ($value !== []) {
                    $values[] = SettingTokens::value($value, $source, $scope);
                }
            }
        }
        return new Setting($name, $settingScope, $action, $values, $source);
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
