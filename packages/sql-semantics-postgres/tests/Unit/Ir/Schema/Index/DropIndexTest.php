<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexDropMode;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexReference;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\MissingIndex;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(DropIndex::class)]
#[Small]
final class DropIndexTest extends TestCase
{
    public function testToStringPreservesOccurrencesAndDetachesTargetReference(): void
    {
        $reference = new IndexReference(new Identifier('app'), new Identifier('odd"name'), new Identifier('accounts'));
        $value = new DropIndex([&$reference, $reference]);
        $reference = new IndexReference(new Identifier('later'), new Identifier('other'), new Identifier('accounts'));

        self::assertSame(['app', 'app'], array_map(static fn ($target): ?string => $target->schema?->value, $value->targets));
        self::assertSame('DROP INDEX "app"."odd""name", "app"."odd""name" RESTRICT', $value->toString());
    }

    public function testProfileIdReportsTheFixedNativeRelease(): void
    {
        $target = new MissingIndex(new Identifier('removed'), new Identifier('pg_temp'), true);
        $value = new DropIndex([$target], true, mode: IndexDropMode::Concurrently, release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
        self::assertSame('DROP INDEX CONCURRENTLY IF EXISTS "pg_temp"."removed" RESTRICT', $value->toString());
    }
}
