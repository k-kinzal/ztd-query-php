<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type\MySql;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\Type\TypeTokens;
use SqlSemantics\Ast\Type\TypeWords;

/**
 * Resolves MySQL type keyword aliases using their grammar terminals.
 * @visibility SqlSemantics
 */
final class DeclarationWords
{
    /**
     * Keeps national character-set selection, numeric flags, and encoding out of the type name.
     */
    public static function read(Node $source): TypeWords
    {
        $encoding = CharacterEncoding::read($source);
        $optionTokens = [];
        foreach (Tree::outer($source, ['opt_binary', 'opt_charset_with_opt_binary', 'opt_bin_mod']) as $option) {
            array_push($optionTokens, ...$option->tokens());
        }
        $tokens = array_values(array_filter(TypeTokens::outer($source), static fn (Token $token): bool => !in_array($token, $optionTokens, true)));
        $unsigned = array_filter($tokens, static fn (Token $token): bool => in_array($token->name, ['UNSIGNED_SYM', 'UNSIGNED', 'ZEROFILL_SYM', 'ZEROFILL'], true)) !== [];
        $words = array_map(self::word(...), array_values(array_filter($tokens, static fn (Token $token): bool => !in_array($token->name, ['SIGNED_SYM', 'UNSIGNED_SYM', 'UNSIGNED', 'ZEROFILL_SYM', 'ZEROFILL'], true))));
        $national = Tree::outer($source, ['nchar', 'nvarchar']) !== [];
        $name = implode(' ', $words);
        $words = match (true) {
            $national => [Tree::outer($source, ['nvarchar']) !== [] ? 'VARCHAR' : 'CHAR'],
            $name === 'LONG VARBINARY' => ['MEDIUMBLOB'],
            in_array($name, ['LONG', 'LONG VARCHAR', 'LONG CHAR VARYING'], true) => ['MEDIUMTEXT'],
            default => $words,
        };
        return new TypeWords($words, TypeTokens::numbers($source), $unsigned, $encoding->name, $encoding->binary, $national);
    }

    /**
     * Keyword aliases have the same meaning regardless of their SQL spelling.
     */
    public static function word(Token $token): string
    {
        return match ($token->name) {
            'INT_SYM' => 'INTEGER', 'TINYINT_SYM', 'TINYINT' => 'TINYINT', 'SMALLINT_SYM', 'SMALLINT' => 'SMALLINT', 'MEDIUMINT_SYM', 'MEDIUMINT' => 'MEDIUMINT', 'BIGINT_SYM', 'BIGINT' => 'BIGINT',
            'CHAR_SYM' => 'CHAR', 'VARCHAR_SYM', 'VARCHAR' => 'VARCHAR', 'FLOAT_SYM' => 'FLOAT', 'DOUBLE_SYM' => 'DOUBLE', 'DECIMAL_SYM', 'FIXED_SYM', 'NUMERIC_SYM' => 'NUMERIC',
            'YEAR_SYM' => 'YEAR', 'GEOMETRYCOLLECTION_SYM', 'GEOMETRYCOLLECTION' => 'GEOMETRYCOLLECTION',
            default => strtoupper($token->text),
        };
    }
}
