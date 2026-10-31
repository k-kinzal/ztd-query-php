<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;

#[CoversClass(ShowColumns::class)]
#[Medium]
final class ShowColumnsTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW COLUMNS FROM t');
        self::assertInstanceOf(ShowColumns::class, $show);
        self::assertSame(6, count($show->fields() ?? []));
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\UndeclaredTable::class, $show->table->relationInformation()->table);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW COLUMNS FROM t');
        self::assertInstanceOf(ShowColumns::class, $show);
        self::assertSame('Field', $show->relationInformation()->shape->slots[0]->name?->value);
    }

    #[DataProvider('providerWrittenForms')]
    public function testRenderPreservesTheColumnsAndFieldsAlternatives(string $release, string $sql, bool $fields): void
    {
        $show = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        self::assertInstanceOf(ShowColumns::class, $show);
        self::assertSame($fields, $show->fieldsKeyword);
        self::assertSame($sql, $show->toString());
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerWrittenForms(): iterable
    {
        foreach (['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.4.7'] as $release) {
            foreach (['COLUMNS', 'FIELDS'] as $word) {
                yield $release . ' ' . $word => [$release, 'SHOW ' . $word . ' FROM t', $word === 'FIELDS'];
                yield $release . ' full ' . $word => [$release, "SHOW FULL $word IN t FROM shop LIKE 'a%'", $word === 'FIELDS'];
                yield $release . ' filtered ' . $word => [$release, "SHOW $word FROM t IN shop WHERE Field = 'a'", $word === 'FIELDS'];
            }
        }
        yield 'extended fields' => ['mysql-8.4.7', 'SHOW EXTENDED FIELDS FROM t', true];
        yield 'extended full fields' => ['mysql-8.4.7', "SHOW EXTENDED FULL FIELDS FROM t IN shop LIKE 'a%'", true];
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW COLUMNS FROM t', (new Semantics(Dialect::MySql))->analyze('SHOW COLUMNS FROM t')->toString());
    }
}
