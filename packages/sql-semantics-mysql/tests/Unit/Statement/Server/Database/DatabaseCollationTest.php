<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;

#[CoversClass(DatabaseCollation::class)]
#[Medium]
final class DatabaseCollationTest extends TestCase
{
    public function testRenderWritesCollate(): void
    {
        self::assertSame('CREATE DATABASE d COLLATE latin1_bin', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('create database d default collate = latin1_bin')->toString());
    }
}
