<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Json;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Operator\Comparison\Pattern;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use MySqlMemory\Value\Json\JsonSearch;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The functions that search JSON documents: JSON_EXTRACT, JSON_CONTAINS, JSON_CONTAINS_PATH,
 * JSON_SEARCH and JSON_OVERLAPS.
 *
 * Each reads its document first, and is NULL when it is NULL before any other argument is read;
 * the paths are then read in order, and a NULL path makes the result NULL before the paths after
 * it are read. JSON_EXTRACT answers the value one path that is not wild selects, else an array of
 * what every path selects in turn, and NULL when nothing is selected. JSON_CONTAINS tells whether
 * a candidate is contained in the document, or in the value a path that may not be wild selects,
 * NULL when it selects nothing ({@see JsonSearch::contains()}). JSON_CONTAINS_PATH takes 'one' or
 * 'all' in any case (ER_JSON_BAD_ONE_OR_ALL_ARG otherwise) and stops at the first path that
 * decides it. JSON_SEARCH reads its escape first, then the document, the 'one' or 'all'
 * argument and the paths, and is NULL for a NULL pattern; it matches the strings of the document,
 * or of what its paths select, with a LIKE pattern in the collation the strings of the document
 * (coercible utf8mb4_bin), the pattern and the escape aggregate to, the escape a backslash for
 * NULL and none for an empty string, one longer than a character refused (ER_WRONG_ARGUMENTS);
 * it answers the path of the first match for 'one', and the paths of every match, each once, for
 * 'all', a single one not in an array (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Searches
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('JSON_EXTRACT', 2, -1, $this->extract(...)),
            new Routine('JSON_CONTAINS', 2, 3, $this->contains(...)),
            new Routine('JSON_CONTAINS_PATH', 3, -1, $this->containsPath(...)),
            new Routine('JSON_SEARCH', 3, -1, $this->search(...)),
            new Routine('JSON_OVERLAPS', 2, 2, $this->overlaps(...)),
        ];
    }

    /**
     * JSON_EXTRACT(document, path, ...): what the paths select.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the document or a path is not valid
     */
    public function extract(Frame $frame, array $arguments): ?string
    {
        $document = Jsons::read($arguments[0], $frame, 1, 'json_extract');
        if ($document === null) {
            return null;
        }
        $found = [];
        $wild = count($arguments) > 2;
        foreach (array_slice($arguments, 1) as $argument) {
            $path = Jsons::path($argument, $frame);
            if ($path === null) {
                return null;
            }
            $wild = $wild || $path->wild();
            array_push($found, ...$path->select($document));
        }
        if ($found === []) {
            return null;
        }

        return ($wild ? new JsonNode(JsonKind::Array, $found) : $found[0])->store();
    }

    /**
     * JSON_CONTAINS(target, candidate [, path]): whether the candidate is contained in the target or in what the path selects.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When a document or the path is not valid, or the path is wild
     */
    public function contains(Frame $frame, array $arguments): ?int
    {
        $target = Jsons::read($arguments[0], $frame, 1, 'json_contains');
        if ($target === null) {
            return null;
        }
        $candidate = Jsons::read($arguments[1], $frame, 2, 'json_contains');
        if ($candidate === null) {
            return null;
        }
        if (isset($arguments[2])) {
            $path = Jsons::path($arguments[2], $frame);
            if ($path === null) {
                return null;
            }
            Modifications::single($path, $frame);
            $target = $path->extract($target);
            if ($target === null) {
                return null;
            }
        }

        return JsonSearch::contains($target, $candidate) ? 1 : 0;
    }

    /**
     * JSON_CONTAINS_PATH(document, 'one' | 'all', path, ...): whether one or all of the paths select something.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the document or a path is not valid, or the second argument is neither 'one' nor 'all'
     */
    public function containsPath(Frame $frame, array $arguments): ?int
    {
        $document = Jsons::read($arguments[0], $frame, 1, 'json_contains_path');
        if ($document === null) {
            return null;
        }
        $all = self::oneOrAll($arguments[1], $frame, 'json_contains_path');
        if ($all === null) {
            return null;
        }
        foreach (array_slice($arguments, 2) as $argument) {
            $path = Jsons::path($argument, $frame);
            if ($path === null) {
                return null;
            }
            $selected = $path->select($document) !== [];
            if ($selected !== $all) {
                return $selected ? 1 : 0;
            }
        }

        return $all ? 1 : 0;
    }

    /**
     * Reads the 'one' or 'all' argument: whether it is 'all', or null when it is NULL.
     *
     * @param string $function The function name the server writes in its message
     *
     * @throws SqlError When it is neither 'one' nor 'all'
     */
    public static function oneOrAll(Evaluable $argument, Frame $frame, string $function): ?bool
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $text = strtolower((string) Convert::toText($value, $argument->domain()));
        if ($text !== 'one' && $text !== 'all') {
            throw DataError::JsonBadOneOrAll->error($function);
        }

        return $text === 'all';
    }

    /**
     * JSON_SEARCH(document, 'one' | 'all', pattern [, escape [, path ...]]): the paths of the strings that match the pattern.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the document or a path is not valid, the second argument is neither 'one' nor 'all', or the escape is longer than a character
     */
    public function search(Frame $frame, array $arguments): ?string
    {
        $escape = isset($arguments[3]) ? self::escape($arguments[3], $frame) : ['\\', Charset::known('ascii')];
        $document = Jsons::read($arguments[0], $frame, 1, 'json_search');
        if ($document === null) {
            return null;
        }
        $all = self::oneOrAll($arguments[1], $frame, 'json_search');
        if ($all === null) {
            return null;
        }
        $roots = [];
        foreach (array_slice($arguments, 4) as $argument) {
            $path = Jsons::path($argument, $frame);
            if ($path === null) {
                return null;
            }
            $roots[] = $path;
        }
        $search = $arguments[2]->evaluate($frame);
        if ($search === null) {
            return null;
        }
        $strings = Domain::string(4294967295, Collation::known('utf8mb4_bin'))->withCollation(Collation::known('utf8mb4_bin'), Coercibility::Coercible);
        $collation = Collations::aggregate(isset($arguments[3]) ? [$strings, $arguments[2]->domain(), $arguments[3]->domain()] : [$strings, $arguments[2]->domain()], 'like', Collation::named((string) $frame->context->variables->read('collation_connection')) ?? Collation::known('utf8mb4_0900_ai_ci'))[0];
        $matcher = new Pattern($arguments[2], $arguments[2], null, $collation, false, Domain::integer());
        $searchDomain = $arguments[2]->domain();
        $pattern = $matcher->tokens($matcher->characters(Encoding::convert((string) Convert::toText($search, $searchDomain), $searchDomain->kind === Kind::String ? $searchDomain->collation->charset : Charset::known('utf8mb4'), $collation->charset)), self::escaping($escape, $collation));
        $paths = JsonSearch::paths($document);
        $found = [];
        foreach ($roots === [] ? [new JsonPath([])] : $roots as $path) {
            foreach ($path->select($document) as $selected) {
                foreach ($selected->descendants() as $node) {
                    if ($node->type === JsonKind::String && $matcher->match($matcher->characters(Encoding::convert($node->scalar(), Charset::known('utf8mb4'), $collation->charset)), 0, $pattern, 0)) {
                        $found[spl_object_id($node)] ??= new JsonNode(JsonKind::String, $paths[spl_object_id($node)] ?? '$');
                        if (!$all) {
                            return $found[spl_object_id($node)]->text();
                        }
                    }
                }
            }
        }
        if ($found === []) {
            return null;
        }

        return count($found) === 1 ? array_values($found)[0]->text() : (new JsonNode(JsonKind::Array, array_values($found)))->text();
    }

    /**
     * Answers the escape character of JSON_SEARCH in the character set of a collation; a character written in more than one byte escapes nothing in a binary collation (verified on a live 8.4 server).
     *
     * @param array{string, Charset} $escape The escape and its character set
     */
    public static function escaping(array $escape, Collation $collation): string
    {
        $converted = Encoding::convert($escape[0], $escape[1], $collation->charset);

        return strlen($escape[0]) > 1 && $collation->binaryOrder() ? '' : $converted;
    }

    /**
     * Reads the escape character of JSON_SEARCH and its character set: a backslash for NULL, none for an empty string.
     *
     * An escape of more than one character, in bytes for a binary string, is refused.
     *
     * @return array{string, Charset}
     *
     * @throws SqlError When the escape is longer than one character
     */
    public static function escape(Evaluable $argument, Frame $frame): array
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return ['\\', Charset::known('ascii')];
        }
        $domain = $argument->domain();
        $text = (string) Convert::toText($value, $domain);
        $charset = $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4');
        if (count(Encoding::characters($text, $charset)) > 1) {
            throw StatementError::WrongArguments->error('ESCAPE');
        }

        return [$text, $charset];
    }

    /**
     * Reads a string argument as utf8mb4 text, or null when it is NULL.
     *
     * @throws SqlError When the argument cannot be evaluated
     */
    public static function text(Evaluable $argument, Frame $frame): ?string
    {
        $value = $argument->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $argument->domain();
        $text = (string) Convert::toText($value, $domain);

        return $domain->kind === Kind::String && $domain->collation->charset !== Charset::binary() ? Encoding::convert($text, $domain->collation->charset, Charset::known('utf8mb4')) : $text;
    }

    /**
     * JSON_OVERLAPS(left, right): whether the documents have an element or a member in common.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When a document is not valid
     */
    public function overlaps(Frame $frame, array $arguments): ?int
    {
        $left = Jsons::read($arguments[0], $frame, 1, 'json_overlaps');
        if ($left === null) {
            return null;
        }
        $right = Jsons::read($arguments[1], $frame, 2, 'json_overlaps');
        if ($right === null) {
            return null;
        }

        return JsonSearch::overlaps($left, $right) ? 1 : 0;
    }
}
