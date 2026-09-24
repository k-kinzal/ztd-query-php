<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Definition;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;

/**
 * Reads named declaration options without requiring callers to traverse grammar nodes.
 *
 * @visibility SqlSemantics
 */
final class OptionReader
{
    /**
     * @param list<string> $boundaries Nested declarations that own their own options
     * @return array<string, string|bool|list<string>>
     */
    public static function read(Node $source, Identifiers $identifiers, array $boundaries = []): array
    {
        $result = [];
        $names = ['create_table_option', 'table_option', 'reloption_elem', 'def_elem', 'SeqOptElem', 'table_access_method_clause', 'OptTableSpace', 'opt_tablespace', 'OnCommitOption', 'OptTemp', 'opt_temporary', 'index_option', 'common_index_option', 'all_key_opt', 'fulltext_key_opt', 'opt_nulls_distinct', 'opt_unique_null_treatment', 'ifnotexists', 'opt_concurrently', 'opt_if_not_exists', 'PartitionSpec'];
        foreach (Tree::outer($source, [...$boundaries, ...$names]) as $node) {
            if (!in_array($node->name, $boundaries, true)) {
                $result = array_replace($result, self::option($node->tokens(), $identifiers));
            }
        }
        return $result;
    }

    /**
     * @param list<Node> $attributes
     * @return array<string, string|bool|list<string>>
     */
    public static function column(Node $source, array $attributes, Identifiers $identifiers): array
    {
        $result = [];
        foreach ([...$attributes, ...Tree::outer($source, ['opt_charset_with_opt_binary', 'opt_binary', 'opt_column_format', 'opt_storage_media', 'field_option', 'opt_stored_attribute'])] as $attribute) {
            $words = array_map(static fn ($token): string => strtoupper($token->text), $attribute->tokens());
            if (in_array('AUTOINCREMENT', $words, true) || in_array('AUTO_INCREMENT', $words, true)) {
                $result['auto_increment'] = true;
            }
            if (in_array('IDENTITY', $words, true)) {
                $result['identity'] = in_array('ALWAYS', $words, true) ? 'always' : 'by-default';
                $result = array_replace($result, self::read($attribute, $identifiers));
            } elseif (in_array($words[0] ?? '', ['COLLATE', 'COMMENT', 'CHARACTER', 'CHARSET', 'STORAGE', 'COMPRESSION', 'COLUMN_FORMAT', 'VISIBLE', 'INVISIBLE', 'ENGINE_ATTRIBUTE', 'SECONDARY_ENGINE_ATTRIBUTE', 'ON', 'SRID', 'SIGNED', 'UNSIGNED', 'ZEROFILL', 'BINARY'], true)) {
                $result = array_replace($result, self::option($attribute->tokens(), $identifiers));
            }
            if (in_array('STORED', $words, true) || in_array('VIRTUAL', $words, true)) {
                $result['generated_storage'] = in_array('STORED', $words, true) ? 'stored' : 'virtual';
            }
        }
        return $result;
    }

    /**
     * @param list<Token> $tokens
     * @return array<string, string|bool|list<string>>
     */
    public static function option(array $tokens, Identifiers $identifiers): array
    {
        if ($tokens === []) {
            return [];
        }
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $tokens);
        [$key, $prefix] = self::name($tokens, $identifiers);
        $values = array_values(array_filter(array_slice($tokens, $prefix), static fn (Token $token): bool => !in_array($token->text, ['=', ',', '(', ')', '.'], true)));
        $decoded = array_map(static fn (Token $token): string => self::value($token, $identifiers), $values);
        if ($key === 'without' && array_slice($words, 1) === ['ROWID']) {
            return ['without_rowid' => true];
        }
        if ($key === 'nulls') {
            return ['nulls_distinct' => !in_array('NOT', array_slice($words, 1), true)];
        }
        if ($key === 'if' && array_slice($words, 1) === ['NOT', 'EXISTS']) {
            return ['if_not_exists' => true];
        }
        return [$key => $decoded === [] ? true : (count($decoded) === 1 ? $decoded[0] : $decoded)];
    }

    /**
     * Reads the normalized option name and the offset of its value.
     *
     * @param non-empty-list<Token> $tokens
     * @return array{string, int}
     */
    public static function name(array $tokens, Identifiers $identifiers): array
    {
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $tokens);
        $prefix = match (true) {
            array_slice($words, 0, 3) === ['DEFAULT', 'CHARACTER', 'SET'] => 3,
            in_array(implode(' ', array_slice($words, 0, 2)), ['CHARACTER SET', 'DEFAULT CHARSET', 'DEFAULT COLLATE', 'DATA DIRECTORY', 'INDEX DIRECTORY', 'ON COMMIT', 'ON UPDATE', 'PARTITION BY', 'START WITH', 'INCREMENT BY', 'WITH PARSER'], true) => 2,
            default => 1,
        };
        $key = $prefix === 1 && in_array(substr($tokens[0]->text, 0, 1), ['"', '`', '['], true) ? $identifiers->name($tokens[0]) : strtolower(implode('_', array_slice($words, 0, $prefix)));
        $key = match ($key) {
            'default_character_set', 'character_set', 'default_charset', 'charset' => 'character_set',
            'default_collate', 'collate' => 'collation',
            'start_with' => 'start', 'increment_by' => 'increment',
            'temp' => 'temporary',
            default => $key,
        };
        if (($tokens[$prefix]->text ?? '') === '.' && isset($tokens[$prefix + 1])) {
            $key .= '.' . $identifiers->name($tokens[$prefix + 1]);
            $prefix += 2;
        }
        return [$key, $prefix];
    }

    /**

     * Decodes literal or identifier quoting without changing unquoted option values.

     */
    public static function value(Token $token, Identifiers $identifiers): string
    {
        if (str_starts_with($token->text, "'")) {
            return str_replace("''", "'", substr($token->text, 1, -1));
        }
        return in_array(substr($token->text, 0, 1), ['"', '`', '['], true) ? $identifiers->name($token) : $token->text;
    }
}
