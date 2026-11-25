<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexes;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(DropIndexes::class)]
#[Medium]
final class DropIndexesTest extends TestCase
{
    public function testToStringRendersOneOrderedNativeStatement(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY by_a (a), KEY by_b (b))']);
        $value = $semantics->analyze('ALTER TABLE app.t LOCK=NONE, DROP INDEX by_b, DROP KEY by_a, ALGORITHM=INPLACE', $catalog);
        self::assertInstanceOf(DropIndexes::class, $value);
        $rebuilt = new DropIndexes($value->targets, $value->release, $value->policy);
        $tick = chr(96);
        $expected = str_replace('%', $tick, 'ALTER TABLE %app%.%t% DROP INDEX %by_b%, DROP INDEX %by_a%, ALGORITHM=INPLACE, LOCK=NONE');

        self::assertSame($expected, $value->toString());
        self::assertSame($expected, $rebuilt->toString());
    }

    public function testProfileIdIdentifiesTheAtomicIndexRemoval(): void
    {
        $semantics = new Semantics('mysql-5.6.51');
        $catalog = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY by_a (a), KEY by_b (b))']);
        $value = $semantics->analyze('ALTER IGNORE TABLE app.t DROP KEY by_a, DROP KEY by_b', $catalog);
        self::assertInstanceOf(DropIndexes::class, $value);

        self::assertTrue($value->ignore);
        self::assertSame('mysql-5.6.51/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/drop-index-set', $value->profileId());
    }
}
