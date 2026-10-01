<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use InvalidArgumentException;
use SqlParser\Parser\SqlParser;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Core\Analysis\TriviaReader;
use SqlSemantics\Core\Builder as Composer;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\Mode as SessionMode;
use SqlSemantics\Core\Parameters;
use SqlSemantics\Core\Platform as Contract;
use SqlSemantics\Core\Policy;
use SqlSemantics\Core\SearchPath as SessionSearchPath;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;

/**
 * Assembles Sqlite semantic behavior from independent core contracts.
 *
 * @visibility SqlSemantics
 */
final class Platform implements Contract
{
    /**
     * Configures the selected grammar release and parameter syntax; this database has no session mode.
     *
     * @throws InvalidArgumentException When a mode is given
     */
    public function parser(?string $version = null, ?SessionMode $mode = null, Parameters $parameters = Parameters::Native): SqlParser
    {
        if ($mode !== null) {
            throw new InvalidArgumentException('This database reads SQL under no session mode; ' . $mode::class . ' given.');
        }

        return new SqliteParser($version);
    }

    /**
     * Supplies operations without retaining parser or grammar-model objects.
     */
    public function operations(Language $language): Policy\OperationRules
    {
        return new Analysis\OperationReader();
    }

    /**
     * Keeps the exact declaration objects and the database's namespace policies.
     * @param non-empty-list<string> $path
     */
    public function catalog(array $path, bool $complete, Table ...$tables): Catalog
    {
        $schemas = array_map(static fn (string $schema): Name => new Name($schema, Quote::Double), $path);
        return new Catalog(new SearchPath(...$schemas), Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, $complete, null, new Name('main'), ...$tables);
    }

    /**
     * Composes this database's values for a language.
     */
    public function builder(Language $language): Composer
    {
        return new Builder($language);
    }

    /**
     * Loads this package's statement construction map for the resolved release.
     */
    public function values(string $version): \SqlSemantics\Core\Analysis\ValueReader
    {
        return \SqlSemantics\Core\Analysis\ValueReader::fromFile(dirname(__DIR__) . '/resources/mapping/' . basename($version) . '.php', new TriviaReader());
    }

    /**
     * Supplies the literal decoder for the resolved language.
     */
    public function literals(Language $language): Policy\LiteralRules
    {
        return new LiteralDecoder();
    }

    /**
     * Searches temporary relations before main and the explicitly supplied attached schemas.
     * @throws InvalidArgumentException When main does not follow the temporary namespace
     */
    public function searchPath(?SessionSearchPath $path = null): array
    {
        $schemas = $path === null ? ['main'] : $path->schemas;
        if (strcasecmp($schemas[0], 'temp') === 0) {
            array_shift($schemas);
        }
        if ($schemas === [] || strcasecmp($schemas[0], 'main') !== 0) {
            throw new InvalidArgumentException('SQLite searches temp, main, then the attached schemas.');
        }
        return ['temp', ...$schemas];
    }

    /**
     * @return array{string, string}
     */
    public function statementNames(): array
    {
        return ['input', 'cmd'];
    }

    /**
     * Maps the supported grammar productions onto semantic roles.
     */
    public function syntax(): Policy\SyntaxRules
    {
        return new Policy\SyntaxRules([
            'autoIncrement' => ['autoinc'],
            'generationStorage' => ['generated'],
            'generationClause' => [],
            'columnName' => ['nm'],
            'declaredType' => ['typetoken'],
            'expression' => ['expr'],
            'tableElements' => ['columnlist'],
            'createTable' => ['create_table'],
            'createHeader' => ['create_table'],
            'tableName' => ['nm'],
            'tableConstraint' => ['tcons'],
        ]);
    }

    /**
     * Names the positions where the grammar writes table names, and the forms that declare, drop, or merely name tables.
     *
     * The body of a common table expression names every one of its WITH
     * clause, itself and later ones included, whether or not the clause is
     * recursive. The table INSERT, UPDATE, or DELETE writes to is always a
     * table, never a common table expression.
     */
    public function relations(): Policy\RelationRules
    {
        return new Policy\RelationRules(
            nameSymbols: ['fullname', 'xfullname', 'add_column_fullname'],
            declarations: [['rule' => 'create_table', 'pair' => ['nm', 'dbnm'], 'conditional' => 'ifnotexists']],
            drops: [['rule' => 'cmd', 'requires' => ['DROP', 'TABLE', 'fullname'], 'names' => 'fullname', 'conditional' => 'ifexists']],
            commonTableExpressions: [['rule' => 'wqitem', 'name' => 'withnm']],
            ignored: [
                ['rule' => 'cmd', 'requires' => ['createkw', 'VIEW'], 'pair' => ['nm', 'dbnm']],
                ['rule' => 'cmd', 'requires' => ['createkw', 'INDEX'], 'pair' => ['nm', 'dbnm']],
                ['rule' => 'cmd', 'requires' => ['DROP', 'VIEW'], 'name' => 'fullname'],
                ['rule' => 'cmd', 'requires' => ['DROP', 'TRIGGER'], 'name' => 'fullname'],
                ['rule' => 'cmd', 'requires' => ['DROP', 'INDEX'], 'name' => 'fullname'],
            ],
            pairs: [
                ['rule' => 'ccons', 'requires' => ['REFERENCES', 'nm'], 'pair' => ['nm']],
                ['rule' => 'tcons', 'requires' => ['FOREIGN', 'REFERENCES', 'nm'], 'pair' => ['nm']],
                ['rule' => 'seltablist', 'requires' => ['nm', 'dbnm'], 'pair' => ['nm', 'dbnm']],
                ['rule' => 'cmd', 'requires' => ['createkw', 'INDEX', 'ON'], 'pair' => ['nm']],
            ],
            parts: ['xfullname' => ['nm DOT nm AS nm' => [0, 1], 'nm AS nm' => [0]]],
            withClauses: ['with', 'wqlist'],
            recursive: 'RECURSIVE',
            visibility: Policy\WithVisibility::All,
            recursiveVisibility: Policy\WithVisibility::All,
            targets: ['xfullname'],
        );
    }

    /**
     * Supplies names semantics.
     */
    public function names(): Policy\NameRules
    {
        return new NameRules();
    }

    /**
     * Supplies types semantics.
     */
    public function types(): Policy\TypeRules
    {
        return new TypeRules();
    }

    /**
     * Supplies schema semantics.
     */
    public function schema(): Policy\SchemaRules
    {
        return new SchemaRules();
    }

}
