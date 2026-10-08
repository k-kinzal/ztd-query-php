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
}
