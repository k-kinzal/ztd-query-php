<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\TypeSystem;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\DictionaryOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Writes the text search parser, template, dictionary, and configuration commands.
 * @visibility SqlSemantics
 */
final class TextSearch
{
    /**
     * Returns null for statements outside these forms.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateTextSearchParserStatement => self::create('PARSER', $statement->name, ['START' => $statement->start, 'GETTOKEN' => $statement->gettoken, 'END' => $statement->end, 'LEXTYPES' => $statement->lextypes, 'HEADLINE' => $statement->headline]),
            $statement instanceof Statement\CreateTextSearchTemplateStatement => self::create('TEMPLATE', $statement->name, ['INIT' => $statement->init, 'LEXIZE' => $statement->lexize]),
            $statement instanceof Statement\CreateTextSearchConfigurationStatement => self::create('CONFIGURATION', $statement->name, ['PARSER' => $statement->parser]),
            $statement instanceof Statement\CopyTextSearchConfigurationStatement => self::create('CONFIGURATION', $statement->name, ['COPY' => $statement->copied]),
            $statement instanceof Statement\CreateTextSearchDictionaryStatement => new Tree('create-text-search', [Build::keyword('CREATE TEXT SEARCH DICTIONARY'), self::name($statement->name), Build::parentheses(Build::separated([self::setting('TEMPLATE', $statement->template), ...array_map(self::option(...), $statement->options)]))]),
            $statement instanceof Statement\AlterTextSearchDictionaryStatement => new Tree('alter-text-search', [Build::keyword('ALTER TEXT SEARCH DICTIONARY'), self::name($statement->dictionary), Build::parentheses(Build::separated(array_map(self::option(...), $statement->options)))]),
            default => self::mapping($statement),
        };
    }

    /**
     * Writes the configuration mapping commands; returns null for other statements.
     * @throws InvalidStructure
     */
    public static function mapping(BoundStatement $statement): ?Tree
    {
        $alter = static fn (QualifiedName $configuration): Tree => new Tree('alter-text-search', [Build::keyword('ALTER TEXT SEARCH CONFIGURATION'), self::name($configuration)]);
        return match (true) {
            $statement instanceof Statement\MapTextSearchTokensStatement => new Tree('alter-text-search', [$alter($statement->configuration), Build::keyword($statement->change->value . ' MAPPING FOR'), self::tokens($statement->tokenTypes), Build::keyword('WITH'), Build::separated(array_map(self::name(...), $statement->dictionaries))]),
            $statement instanceof Statement\ReplaceTextSearchDictionaryStatement => new Tree('alter-text-search', [$alter($statement->configuration), Build::keyword('ALTER MAPPING'), ...($statement->tokenTypes === null ? [] : [Build::keyword('FOR'), self::tokens($statement->tokenTypes)]), Build::keyword('REPLACE'), self::name($statement->dictionary), Build::keyword('WITH'), self::name($statement->replacement)]),
            $statement instanceof Statement\DropTextSearchMappingStatement => new Tree('alter-text-search', [$alter($statement->configuration), Build::keyword($statement->ifExists ? 'DROP MAPPING IF EXISTS FOR' : 'DROP MAPPING FOR'), self::tokens($statement->tokenTypes)]),
            default => null,
        };
    }

    /**
     * CREATE TEXT SEARCH with the present function or object settings.
     * @param array<string, QualifiedName|null> $settings
     */
    public static function create(string $class, QualifiedName $name, array $settings): Tree
    {
        $present = [];
        foreach ($settings as $key => $value) {
            if ($value !== null) {
                $present[] = self::setting($key, $value);
            }
        }
        return new Tree('create-text-search', [Build::keyword('CREATE TEXT SEARCH ' . $class), self::name($name), Build::parentheses(Build::separated($present))]);
    }

    /**
     * One KEY = name setting.
     */
    public static function setting(string $key, QualifiedName $value): Tree
    {
        return new Tree('text-search-setting', [Build::keyword($key . ' ='), self::name($value)]);
    }

    /**
     * A template option as an identifier and its optional text argument.
     * @throws InvalidStructure
     */
    public static function option(DictionaryOption $option): Tree
    {
        return new Tree('dictionary-option', [Build::identifier([$option->name], Dialect::PostgreSql), ...($option->value === null ? [] : [Build::keyword('='), TypeDefinitions::text($option->value)])]);
    }

    /**
     * A qualified object name.
     */
    public static function name(QualifiedName $name): Tree
    {
        return Build::identifier($name->parts, Dialect::PostgreSql);
    }

    /**
     * Token type names as identifiers.
     * @param non-empty-list<string> $tokenTypes
     */
    public static function tokens(array $tokenTypes): Tree
    {
        return Build::separated(array_map(static fn (string $token): Tree => Build::identifier([$token], Dialect::PostgreSql), $tokenTypes));
    }
}
