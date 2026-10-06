<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationAction::class)]
#[Medium]
final class PublicationActionTest extends TestCase
{
    public function testDropIsSpelledDrop(): void
    {
        self::assertSame('ALTER PUBLICATION p DROP TABLES IN SCHEMA s', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER PUBLICATION p DROP TABLES IN SCHEMA s')->toString());
    }
}
