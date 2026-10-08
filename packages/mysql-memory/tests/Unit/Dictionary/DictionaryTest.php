<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dictionary::class)]
#[Small]
final class DictionaryTest extends TestCase
{
    public function testSchemaFindsADatabaseByItsExactName(): void
    {
        $schema = new Schema('Shop');
        $dictionary = new Dictionary(['Shop' => $schema]);

        self::assertSame([$schema, null], [$dictionary->schema('Shop'), $dictionary->schema('shop')]);
    }

    public function testTableFindsATableOfADatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT)');
        $dictionary = $session->instance->dictionary;

        $table = $dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['d', 't'], [$table->definition->schema, $table->definition->name]);
    }

    public function testTableAnswersNullForAnUnknownTableOrDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $dictionary = $session->instance->dictionary;

        self::assertSame([null, null], [$dictionary->table('d', 'missing'), $dictionary->table('nowhere', 't')]);
    }

    public function testDeclarationsListsTheDeclarationOfEveryTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.a (x INT)');
        $session->query('CREATE TABLE d.b (y INT)');

        $declarations = $session->instance->dictionary->declarations();

        self::assertSame(['a', 'b'], [$declarations[0]->name->name->value, $declarations[1]->name->name->value]);
    }

    public function testDeclarationsListsTheViewsAfterTheTablesOfADatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE VIEW v AS SELECT 1 AS x');
        $session->query('CREATE TABLE t (y INT)');

        $declarations = $session->instance->dictionary->declarations();

        self::assertSame([['t', null], ['v', 'd']], [[$declarations[0]->name->name->value, $declarations[0]->name->schema?->value], [$declarations[1]->name->name->value, $declarations[1]->name->schema?->value]]);
    }

    public function testDeclarationsIsEmptyWithoutTables(): void
    {
        $dictionary = new Dictionary(['d' => new Schema('d')]);

        self::assertSame([], $dictionary->declarations());
    }

    public function testSchemaFindsInformationSchemaInAnyCase(): void
    {
        $dictionary = (new Instance())->dictionary;

        self::assertSame(['information_schema', null], [$dictionary->schema('Information_Schema')?->name, $dictionary->schema('MYSQL')]);
    }

    public function testDeclarationsDeclareTheSystemTablesLast(): void
    {
        $dictionary = (new Instance())->dictionary;
        $dictionary->schemas['d'] = new Schema('d');

        self::assertSame('information_schema', $dictionary->declarations()[0]->name->schema?->value);
    }

    public function testStoreStoresATemporaryTableAmongTheTemporaryTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TEMPORARY TABLE t (b INT)');

        self::assertSame(['b', 'a'], [$session->instance->dictionary->table('d', 't')?->definition->columns[0]->name, $session->instance->dictionary->schema('d')?->table('t')?->definition->columns[0]->name]);
    }

    public function testReleaseRemovesATemporaryTableBeforeABaseTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TEMPORARY TABLE t (b INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        $session->instance->dictionary->release($table, 'd', 't');

        self::assertSame('a', $session->instance->dictionary->table('d', 't')?->definition->columns[0]->name);
    }

    public function testRetargetMakesTheReferencingKeysFollowARename(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (pid INT, FOREIGN KEY (pid) REFERENCES p(id)); RENAME TABLE p TO p2');

        self::assertSame('p2', $session->instance->dictionary->table('d', 'c')?->definition->foreignKeys[0]->parentTable);
    }

    public function testFunctionsAnswersTheTypeEachStoredFunctionReturns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE FUNCTION d.f() RETURNS TINYINT DETERMINISTIC RETURN 1');

        $functions = $session->instance->dictionary->functions();

        self::assertSame([['d.f'], \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Tiny], [array_keys($functions), $functions['d.f']->field]);
    }
}
