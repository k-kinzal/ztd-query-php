<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rendering;

use SqlParser\PostgreSql\PostgreSqlVersion;
use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Lowering\Productions;

/**
 * The keyword categories of one PostgreSQL grammar release.
 *
 * Rule: PG-KEYWORD-001. The categories are read from the grammar itself: a
 * keyword terminal belongs to `unreserved_keyword`, `col_name_keyword`,
 * `type_func_name_keyword` or `reserved_keyword`, and independently to
 * `bare_label_keyword`. The grammar accepts a keyword as a name through
 * `ColId` (unreserved and column-name keywords), `type_function_name`
 * (unreserved and type-or-function-name keywords) and `ColLabel` (every
 * keyword). The first part of a dotted name is read as `ColId` in some
 * positions and as `type_function_name` in others, so a qualifier is written
 * bare only when both accept it. Source: https://www.postgresql.org/docs/17/sql-keywords-appendix.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Keywords
{
    /**
     * The nonterminals that turn a keyword terminal into a name.
     */
    public const CATEGORIES = ['unreserved_keyword', 'col_name_keyword', 'type_func_name_keyword', 'reserved_keyword', 'bare_label_keyword'];

    /**
     * @var array<string, array<string, list<string>>>
     */
    private static array $loaded = [];

    /**
     * @param GrammarRelease $release The grammar release whose keywords are read
     */
    public function __construct(private readonly GrammarRelease $release)
    {
    }

    /**
     * Answers the category nonterminals of a lower-case word; none when the word is not a keyword.
     *
     * @return list<string>
     */
    public function categories(string $word): array
    {
        return $this->table()[$word] ?? [];
    }

    /**
     * Tells whether a lower-case word is read as that name when written without quotes at a position.
     */
    public function bare(string $word, NameUse $use): bool
    {
        $categories = $this->categories($word);
        if ($categories === [] || in_array('unreserved_keyword', $categories, true)) {
            return true;
        }

        return match ($use) {
            NameUse::Column, NameUse::Relation, NameUse::Alias => in_array('col_name_keyword', $categories, true),
            NameUse::Routine => in_array('type_func_name_keyword', $categories, true),
            NameUse::Qualifier => false,
            NameUse::Label => true,
        };
    }

    /**
     * Answers the categories of every keyword of the release by lower-case word.
     *
     * @return array<string, list<string>>
     */
    public function table(): array
    {
        if (isset(self::$loaded[$this->release->value])) {
            return self::$loaded[$this->release->value];
        }
        $terminals = [];
        $productions = Productions::load(dirname(__DIR__, 2) . '/resources/productions/' . $this->release->value . '.php');
        foreach ($productions->all() as $signature) {
            [$rule, $terminal] = explode(': ', $signature . ': ');
            if (in_array($rule, self::CATEGORIES, true)) {
                $terminals[$terminal][] = $rule;
            }
        }
        $table = [];
        $artifact = require (new VersionRegistry())->resolve(PostgreSqlVersion::DIALECT, $this->release->value)->keywordPath;
        $words = is_array($artifact) && is_array($artifact['keywords'] ?? null) ? $artifact['keywords'] : [];
        foreach ($words as $word => $terminal) {
            if (is_string($word) && is_string($terminal) && isset($terminals[$terminal])) {
                $table[strtolower($word)] = $terminals[$terminal];
            }
        }

        return self::$loaded[$this->release->value] = $table;
    }
}
