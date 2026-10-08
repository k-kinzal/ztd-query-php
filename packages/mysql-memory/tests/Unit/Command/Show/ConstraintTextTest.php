<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\ConstraintText;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConstraintText::class)]
#[Small]
final class ConstraintTextTest extends TestCase
{
    public function testLinesWritesTheForeignKeysThenTheChecksEachByName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (a INT, CONSTRAINT myfk FOREIGN KEY (a) REFERENCES p(id), CHECK (a > 0), CONSTRAINT aaa CHECK (a < 100))');
        $table = $session->instance->dictionary->table('d', 'c');

        self::assertNotNull($table);
        self::assertSame(['CONSTRAINT `myfk` FOREIGN KEY (`a`) REFERENCES `p` (`id`)', 'CONSTRAINT `aaa` CHECK ((`a` < 100))', 'CONSTRAINT `c_chk_1` CHECK ((`a` > 0))'], (new ConstraintText())->lines($table->definition));
    }

    public function testCheckSaysWhenTheConstraintIsNotEnforced(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, CONSTRAINT x CHECK (a > 0) NOT ENFORCED)');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame('CONSTRAINT `x` CHECK ((`a` > 0)) /*!80016 NOT ENFORCED */', (new ConstraintText())->check($table->definition->checks[0]));
    }

    public function testNameDoublesABacktick(): void
    {
        self::assertSame('`a``b`', (new ConstraintText())->name('a`b'));
    }
}
