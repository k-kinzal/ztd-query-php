<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DatabaseCollation::class)]
#[Medium]
final class DatabaseCollationTest extends TestCase
{
    public function testRenderWritesCollate(): void
    {
        self::assertSame('CREATE DATABASE d COLLATE latin1_bin', (new Inspection('mysql-5.6.51'))->inspect('create database d default collate = latin1_bin')->toString());
    }
}
