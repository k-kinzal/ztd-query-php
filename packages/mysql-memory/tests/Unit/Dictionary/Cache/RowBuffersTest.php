<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Cache;

use MySqlMemory\Dictionary\Cache\RowBuffers;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(RowBuffers::class)]
#[Small]
final class RowBuffersTest extends TestCase
{
    public function testReadInitializesNullableFieldsWithoutTheirDefaultNullBits(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY,n INT DEFAULT NULL,s VARCHAR(20),d DECIMAL(8,2))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([0, 0, '', '0.00'], (new RowBuffers())->read($session->variables, $table));
    }

    public function testRetainKeepsConnectionAndTableIdentitiesSeparate(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY,n INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $buffers = new RowBuffers();
        $other = new stdClass();
        $buffers->retain($session->variables, $table, [2, null]);
        $buffers->retain($other, $table, [1, 10]);

        self::assertSame([2, null], $buffers->read($session->variables, $table));
        self::assertSame([1, 10], $buffers->read($other, $table));
        self::assertSame([0, 0], $buffers->read(new stdClass(), $table));
    }

    public function testMissingKeepsNotNullValuesAndSetsOnlyNullableNullBits(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY,n INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $buffers = new RowBuffers();
        $buffers->retain($session->variables, $table, [1, 10]);
        $buffers->missing($session->variables, $table);

        self::assertSame([1, null], $buffers->read($session->variables, $table));
    }

    public function testCloseEvictsPersistentHandlesOfEveryConnectionButKeepsTemporaryHandles(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY,n INT)');
        $persistent = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($persistent);
        $temporary = clone $persistent;
        $definition = $persistent->definition;
        $temporary->definition = new \MySqlMemory\Dictionary\TableDefinition('d', 't', $definition->columns, $definition->keys, $definition->declaration, temporary: true);
        $buffers = new RowBuffers();
        $other = new stdClass();
        $buffers->retain($session->variables, $persistent, [1, null]);
        $buffers->retain($other, $persistent, [2, null]);
        $buffers->retain($session->variables, $temporary, [3, null]);
        $buffers->close('other');
        self::assertSame([1, null], $buffers->read($session->variables, $persistent));
        $buffers->close('d', 't');

        self::assertSame([0, 0], $buffers->read($session->variables, $persistent));
        self::assertSame([0, 0], $buffers->read($other, $persistent));
        self::assertSame([3, null], $buffers->read($session->variables, $temporary));
    }
}
