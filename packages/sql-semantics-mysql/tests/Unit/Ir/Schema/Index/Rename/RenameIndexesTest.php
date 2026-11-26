<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Rename;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\RenameIndexes;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(RenameIndexes::class)]
#[Medium]
final class RenameIndexesTest extends TestCase
{
    public function testToStringRetainsOneAtomicSwap(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY left_key(a), KEY right_key(b))']);
        $value = $semantics->analyze('ALTER TABLE app.t RENAME KEY left_key TO right_key, RENAME INDEX right_key TO left_key', $context);
        self::assertInstanceOf(RenameIndexes::class, $value);
        $rebuilt = new RenameIndexes($value->owner, $value->moves, [], $value->release, $value->policy);

        self::assertSame('ALTER TABLE `app`.`t` RENAME INDEX `left_key` TO `right_key`, RENAME INDEX `right_key` TO `left_key`', $rebuilt->toString());
        self::assertSame($value->toString(), $rebuilt->toString());
    }

    public function testProfileIdUsesTheLegacyNativeRelease(): void
    {
        $semantics = new Semantics('mysql-5.7.44');
        $context = $semantics->context(['CREATE TABLE app.t (a INT, KEY by_a (a))']);
        $value = $semantics->analyze('ALTER TABLE app.t RENAME KEY t.by_a TO t.new_name', $context);
        self::assertInstanceOf(RenameIndexes::class, $value);

        self::assertSame('mysql-5.7.44/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/rename-indexes', $value->profileId());
    }
}
