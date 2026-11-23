<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\From\SourceDeclarations;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
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

    public function testPrefixHeadingUsesTheFirstOccurrenceOfEachName(): void
    {
        $sqlite = new Inspection();
        $first = $sqlite->inspect('CREATE TABLE first (Key INT)');
        $second = $sqlite->inspect('CREATE TABLE second (key TEXT, middle INT)');
        $query = $sqlite->inspect('SELECT a.Key FROM first a JOIN second b JOIN first c USING (key)', [$first, $second]);
        self::assertInstanceOf(Select::class, $query->statement);
        $from = $query->statement->from;
        self::assertInstanceOf(JoinChain::class, $from);

        $heading = (new SourceDeclarations())->prefixHeading($query->binding(), $from, 1);

        self::assertSame([0, 'Key'], [$heading['key'][0], $heading['key'][1]->name->value]);
        self::assertSame([1, 'middle'], [$heading['middle'][0], $heading['middle'][1]->name->value]);
    }
}
