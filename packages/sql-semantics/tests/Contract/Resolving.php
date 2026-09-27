<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\Assert;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\Forms;
use SqlSemantics\Core\Analysis\NameSites;
use SqlSemantics\Core\Analysis\Relations;
use SqlSemantics\Core\Analysis\Resolver;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\SchemaReader;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Command;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Traversal;

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
            new Resolver($language, new SchemaReader(new Identifiers($dialect), $platform->defaultSchema(), $language->values())),
            $tree,
            $language->values()->statement($tree)->command,
            new Relations($platform->names(), $platform->defaultSchema()),
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
