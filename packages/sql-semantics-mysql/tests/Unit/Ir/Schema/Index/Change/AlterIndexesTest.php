<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Change;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(AlterIndexes::class)]
#[Medium]
final class AlterIndexesTest extends TestCase
{
    public function testToStringKeepsOneOrderedPhysicalOperation(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY a_key(a), KEY b_key(b))']);
        $value = $semantics->analyze('ALTER TABLE app.t RENAME KEY a_key TO b_key, DROP INDEX b_key', $catalog);
        self::assertInstanceOf(AlterIndexes::class, $value);
        $rebuilt = new AlterIndexes($value->owner, $value->actions, $value->unmoved, $value->release, $value->policy);

        self::assertSame('ALTER TABLE `app`.`t` RENAME INDEX `a_key` TO `b_key`, DROP INDEX `b_key`', $rebuilt->toString());
        self::assertSame($value->toString(), $rebuilt->toString());
    }

    public function testProfileIdRetainsTheStockGrammarAndMode(): void
    {
        $semantics = new Semantics('mysql-5.7.44');
        $catalog = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY a_key(a), KEY b_key(b))']);
        $value = $semantics->analyze('ALTER TABLE app.t RENAME KEY a_key TO replacement, DROP KEY b_key', $catalog);
        self::assertInstanceOf(AlterIndexes::class, $value);

        self::assertSame('mysql-5.7.44/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/alter-indexes', $value->profileId());
    }
}
