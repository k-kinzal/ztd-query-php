<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\Assert;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\Forms;
use SqlSemantics\Core\Analysis\NameSites;
use SqlSemantics\Core\Analysis\Relations;
use SqlSemantics\Core\Analysis\Resolver;
use SqlSemantics\Core\Analysis\Scope;
use SqlSemantics\Core\Analysis\Scopes;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\SchemaReader;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Command;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;

/**
 * States how the pieces of table name resolution are assembled for a dialect.
 */
final class Resolving
{
    /**
     * A resolver of the dialect with the tree and command of a statement, and no table in force.
     *
     * @return array{Resolver, Node, Command, Relations}
     */
    public static function of(Dialect $dialect, string $sql): array
    {
        $language = new Language($dialect);
        $platform = $dialect->platform();
        $tree = (new DialectParser($language))->parse($sql);

        return [
            new Resolver($language, new SchemaReader(new Identifiers($dialect), $platform->searchPath()[0], $language->values()), $platform->searchPath()),
            $tree,
            $language->values()->statement($tree)->command,
            new Relations($platform->names(), $platform->searchPath()),
        ];
    }

    /**
     * The name sites of the dialect.
     */
    public static function sites(Dialect $dialect): NameSites
    {
        $language = new Language($dialect);
        $platform = $dialect->platform();

        return new NameSites($language->vocabulary(), $platform->relations(), $platform->names(), new Forms($language->vocabulary()));
    }

    /**
     * The scopes of common table expressions of the dialect.
     */
    public static function scopes(Dialect $dialect): Scopes
    {
        $vocabulary = (new Language($dialect))->vocabulary();

        return new Scopes($vocabulary, $dialect->platform()->relations(), $dialect->platform()->names(), new Forms($vocabulary));
    }

    /**
     * The values of a statement walked with their scopes, in walking order.
     *
     * @return list<array{Element, Scope}>
     */
    public static function walked(Dialect $dialect, Element $command): array
    {
        return self::pairs(self::scopes($dialect)->walk($command));
    }

    /**
     * The values of a walk with their scopes, in walking order.
     *
     * @param iterable<int, array{Element, Scope}> $walk
     * @return list<array{Element, Scope}>
     */
    public static function pairs(iterable $walk): array
    {
        $pairs = [];
        foreach ($walk as $pair) {
            $pairs[] = $pair;
        }

        return $pairs;
    }

    /**
     * The names visible at each leaf of a walk that writes one of the texts, by that text.
     *
     * @param list<array{Element, Scope}> $walked
     * @param list<string> $texts
     * @return array<string, list<string>>
     */
    public static function visibleAt(array $walked, array $texts): array
    {
        $scopes = [];
        foreach ($walked as [$value, $scope]) {
            if ($value->children() === [] && in_array(Writer::render($value), $texts, true)) {
                $scopes[Writer::render($value)] = $scope->names;
            }
        }

        return $scopes;
    }

    /**
     * The outermost value of a grammar rule in a command.
     */
    public static function form(Dialect $dialect, Element $command, string $rule): Element
    {
        $vocabulary = (new Language($dialect))->vocabulary();
        foreach (Traversal::walk($command) as $value) {
            if (($vocabulary->shape($value)['rule'] ?? null) === $rule) {
                return $value;
            }
        }

        return Assert::fail('No value of the rule ' . $rule);
    }
}
