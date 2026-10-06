<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationSchema::class)]
#[Medium]
final class PublicationSchemaTest extends TestCase
{
    public function testIntroducedWhenTheKeywordsAreWritten(): void
    {
        self::assertTrue((new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationSchema(null))->introduced());
    }

    public function testRenderContinuesSchemas(): void
    {
        self::assertSame('CREATE PUBLICATION p FOR TABLES IN SCHEMA CURRENT_SCHEMA, s, CURRENT_SCHEMA', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLES IN SCHEMA CURRENT_SCHEMA, s, CURRENT_SCHEMA')->toString());
    }

    public function testRenderReadsABareNameAfterASchemaAsASchema(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE PUBLICATION p FOR TABLES IN SCHEMA a, b')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\CreatePublication::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationSchema::class, $statement->objects[1]::class);
    }
}
