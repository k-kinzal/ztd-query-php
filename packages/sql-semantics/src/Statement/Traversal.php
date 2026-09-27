<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use Generator;

/**
 * Walks and rewrites statement values without knowing their concrete classes.
 *
 * Every generated value lists its child values and rebuilds itself around
 * replacements, so a statement of any dialect and release can be searched for
 * the values of a role and rewritten from the leaves up.
 *
 * @visibility public
 * @example Finding every value of a role in a statement
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t WHERE b = 1');
 *     count(\SqlSemantics\Statement\Traversal::find($statement->command, \SqlSemantics\Statement\Model\Sqlite\Role\ExprForm::class)) // => 4
 * @example Rewriting the leaves of a statement
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t');
 *     $rename = static fn (\SqlSemantics\Statement\Element $value): \SqlSemantics\Statement\Element => $value instanceof \SqlSemantics\Statement\Model\Sqlite\Value\NmWithIdj_a2015ecf && $value->name === 't' ? $value->withName('u') : $value;
 *     \SqlSemantics\Statement\Writer::render(\SqlSemantics\Statement\Traversal::rewrite($statement->command, $rename)) // => 'SELECT a FROM u'
 */
final class Traversal
{
    /**
     * Yields a value and then every value below it, each before its own children.
     *
     * @return Generator<int, Element, mixed, void>
     */
    public static function walk(Element $root): Generator
    {
        yield $root;
        foreach ($root->children() as $child) {
            yield from self::walk($child);
        }
    }

    /**
     * Collects the values of a class or role, in writing order, the root included.
     *
     * @template T of Element
     * @param class-string<T> $class A value class or a role interface
     * @return list<T>
     */
    public static function find(Element $root, string $class): array
    {
        $found = [];
        foreach (self::walk($root) as $value) {
            if ($value instanceof $class) {
                $found[] = $value;
            }
        }

        return $found;
    }

    /**
     * Rebuilds a value from the leaves up, giving every value, children first, to the function.
     *
     * The function answers the value to keep, which may be the one it was
     * given. A replacement must be a value the position accepts, and the
     * replacement for the root is answered as is.
     *
     * @param callable(Element): Element $replace
     */
    public static function rewrite(Element $root, callable $replace): Element
    {
        return $replace($root->map(static fn (Element $child): Element => self::rewrite($child, $replace)));
    }
}
