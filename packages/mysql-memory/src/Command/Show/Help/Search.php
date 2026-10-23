<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Help;

use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Evaluation\Context;

/**
 * Searches installed help data in topic, keyword and category precedence.
 *
 * A unique topic takes precedence over keywords and categories. Keywords are used only
 * when no topic matches. Otherwise matching categories join the topic list, or a sole
 * category lists its direct topics and children. Verified with independently authored
 * fixtures on native servers; no server implementation is used.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/help.html.
 *
 * @visibility MySqlMemory
 */
final class Search
{
    /**
     * @param array<string, list<list<string>>> $tables The initial help table data
     */
    public function __construct(public readonly array $tables)
    {
    }

    /**
     * Resolves the response shape and rows of a search.
     */
    public function find(string $pattern, Context $context): Response
    {
        $topics = $this->matching('topics', $pattern, $context);
        if ($topics === []) {
            $keys = array_column($this->matching('keywords', $pattern, $context), 0);
            $ids = [];
            foreach ($this->tables['relations'] ?? [] as [$topic, $keyword]) {
                if (count($keys) === 1 && $keyword === $keys[0]) {
                    $ids[] = $topic;
                }
            }
            $topics = array_values(array_filter($this->tables['topics'] ?? [], static fn (array $topic): bool => in_array($topic[0], $ids, true)));
        }
        if (count($topics) === 1) {
            return new Response(['name' => 64, 'description' => 1000, 'example' => 1000], [[$topics[0][1], $topics[0][3], $topics[0][4]]]);
        }
        $categories = $this->matching('categories', $pattern, $context);
        if ($topics === [] && count($categories) === 1) {
            return $this->category($categories[0]);
        }

        return $this->listing($topics, $categories);
    }

    /**
     * Matches table names with the help tables' case-insensitive collation.
     *
     * @return list<list<string>>
     */
    public function matching(string $table, string $pattern, Context $context): array
    {
        $matches = [];
        foreach ($this->tables[$table] ?? [] as $row) {
            if ((new Listing([]))->like([[$row[1]]], $pattern, 0, 'utf8mb3_general_ci', $context) !== []) {
                $matches[] = $row;
            }
        }

        return $matches;
    }

    /**
     * Lists topics before categories, sorting each list by name.
     *
     * @param list<list<string>> $topics The topic rows
     * @param list<list<string>> $categories The category rows
     */
    public function listing(array $topics, array $categories): Response
    {
        $names = array_map(static fn (array $topic): string => $topic[1], $topics);
        $groups = array_map(static fn (array $category): string => $category[1], $categories);
        sort($names, SORT_STRING);
        sort($groups, SORT_STRING);
        $rows = array_map(static fn (string $name): array => [$name, 'N'], $names);
        foreach ($groups as $name) {
            $rows[] = [$name, 'Y'];
        }

        return new Response(['name' => 64, 'is_it_category' => 1], $rows);
    }

    /**
     * Lists the immediate contents of one category.
     *
     * @param list<string> $category The category row
     */
    public function category(array $category): Response
    {
        $topics = array_values(array_filter($this->tables['topics'] ?? [], static fn (array $topic): bool => $topic[2] === $category[0]));
        $children = array_values(array_filter($this->tables['categories'] ?? [], static fn (array $child): bool => $child[2] === $category[0]));
        $listing = $this->listing($topics, $children);

        return new Response(['source_category_name' => 64, 'name' => 64, 'is_it_category' => 1], array_map(static fn (array $row): array => [$category[1], ...$row], $listing->rows));
    }
}
