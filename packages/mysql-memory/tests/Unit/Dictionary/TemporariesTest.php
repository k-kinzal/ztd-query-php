<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\Temporaries;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Temporaries::class)]
#[Small]
final class TemporariesTest extends TestCase
{
    public function testTableFindsATemporaryTableOfTheSession(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $other = $instance->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE t (a INT)');

        self::assertSame([true, false], [$session->temporaries->table('d', 't') !== null, $other->temporaries->table('d', 't') !== null]);
    }

    public function testAddStoresATableUnderItsName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE t (a INT)');
        $table = $session->temporaries->table('d', 't');
        self::assertNotNull($table);
        $temporaries = new Temporaries();

        $temporaries->add($table);

        self::assertSame($table, $temporaries->table('d', 't'));
    }

    public function testRemoveForgetsATable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE t (a INT)');

        $session->temporaries->remove('d', 't');

        self::assertSame([], $session->temporaries->tables);
    }
}
