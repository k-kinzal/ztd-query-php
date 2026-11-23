<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\From\SourceDeclarations;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(SourceDeclarations::class)]
#[Small]
final class SourceDeclarationsTest extends TestCase
{
    public function testBindKeepsTwoOccurrencesWithOneExternalDeclaration(): void
    {
        $sqlite = new Inspection();
        $table = $sqlite->inspect('CREATE TABLE t (a INT)');
        $operation = $sqlite->inspect('SELECT x.a FROM t x INNER JOIN t y ON x.a = y.a', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);

        $bindings = (new SourceDeclarations())->bind($operation->binding(), $operation->statement->from);
        self::assertCount(2, $bindings);
        self::assertNotSame($bindings[0][0], $bindings[1][0]);
        self::assertSame($bindings[0][1], $bindings[1][1]);
    }

    public function testMergeHeadingPreservesCaseAndDeclarationOrder(): void
    {
        $sqlite = new Inspection();
        $table = $sqlite->inspect('CREATE TABLE t (Lane NUMERIC, Key TEXT)');
        $operation = $sqlite->inspect('SELECT Lane FROM t', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertNotNull($operation->statement->from);

        $heading = (new SourceDeclarations())->mergeHeading($operation->binding(), $operation->statement->from);

        self::assertSame(['lane', 'key'], array_keys($heading));
        self::assertSame('Lane', $heading['lane']->name->value);
        self::assertSame('Key', $heading['key']->name->value);
    }
}
