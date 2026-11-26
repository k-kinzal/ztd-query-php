<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SetNames::class)]
#[Medium]
final class SetNamesTest extends TestCase
{
    public function testDeriveItemDerivesNothing(): void
    {
        $set = (new Inspection())->inspect('SET NAMES DEFAULT');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        self::assertInstanceOf(SetNames::class, $set->statement->items[0]);
        self::assertNull($set->statement->items[0]->charset->name);
        self::assertSame([], $set->facts->diagnostics);
    }

    public function testRenderWritesTheCollation(): void
    {
        self::assertSame('SET NAMES utf8 COLLATE DEFAULT', (new Inspection('mysql-5.6.51'))->inspect("set names 'utf8' collate default")->toString());
    }
}
