<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Scope;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSchema;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(ReindexSchema::class)]
#[Small]
final class ReindexSchemaTest extends TestCase
{
    public function testProfileIdPreservesTheExplicitRelease(): void
    {
        $statement = new ReindexSchema(new Identifier('reporting'), release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $statement->profileId());
    }

    public function testToStringQuotesOneSchemaAndRetainsConcurrency(): void
    {
        $statement = new ReindexSchema(new Identifier('reporting."source"'), IndexRebuildMode::Concurrent);

        self::assertSame('REINDEX SCHEMA CONCURRENTLY "reporting.""source"""', $statement->toString());
    }
}
