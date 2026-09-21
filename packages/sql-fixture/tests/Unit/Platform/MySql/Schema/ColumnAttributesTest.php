<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\ColumnAttributes as Subject;
use SqlParser\MySql\MySqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
final class ColumnAttributesTest extends TestCase
{
    public function testReadDefaultsToANullableColumn(): void
    {
        $tree = (new MySqlParser())->parse('CREATE TABLE t (id INT)');
        $attributes = (new Subject())->read($tree->find('field_def')[0]);

        self::assertTrue($attributes->nullable);
        self::assertFalse($attributes->autoIncrement);
        self::assertFalse($attributes->primaryKey);
        self::assertNull($attributes->default);
    }

    public function testReadRecognizesNotNullDefaultAndAutoIncrement(): void
    {
        $sql = 'CREATE TABLE t (id INT NOT NULL DEFAULT 1 AUTO_INCREMENT UNIQUE KEY COMMENT \'x\')';
        $tree = (new MySqlParser())->parse($sql);
        $attributes = (new Subject())->read($tree->find('field_def')[0]);

        self::assertFalse($attributes->nullable);
        self::assertTrue($attributes->autoIncrement);
        self::assertFalse($attributes->primaryKey);
        self::assertNotNull($attributes->default);
        self::assertSame('DEFAULT 1', $attributes->default->text($sql));
    }

    public function testReadTreatsPrimaryKeyAndBareKeyAsPrimary(): void
    {
        $tree = (new MySqlParser())->parse('CREATE TABLE t (a INT PRIMARY KEY, b INT KEY, c INT UNIQUE KEY)');
        $fields = $tree->find('field_def');

        self::assertTrue((new Subject())->read($fields[0])->primaryKey);
        self::assertTrue((new Subject())->read($fields[1])->primaryKey);
        self::assertFalse((new Subject())->read($fields[2])->primaryKey);
    }

    public function testReadLetsAnExplicitNullFollowNotNull(): void
    {
        $tree = (new MySqlParser())->parse('CREATE TABLE t (a INT NOT NULL NULL, b INT NOT SECONDARY)');
        $fields = $tree->find('field_def');

        self::assertTrue((new Subject())->read($fields[0])->nullable);
        self::assertTrue((new Subject())->read($fields[1])->nullable);
    }

    public function testReadTreatsSerialDefaultValueAsAutoIncrement(): void
    {
        $tree = (new MySqlParser())->parse('CREATE TABLE t (a INT SERIAL DEFAULT VALUE)');
        $attributes = (new Subject())->read($tree->find('field_def')[0]);

        self::assertTrue($attributes->autoIncrement);
        self::assertFalse($attributes->nullable);
        self::assertNull($attributes->default);
    }
}
