<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\PublicationMember::class)]
#[Medium]
final class PublicationMemberTest extends TestCase
{
    public function testIntroducedOfABareName(): void
    {
        $statement = (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('CREATE PUBLICATION p FOR TABLE a, b')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\CreatePublication::class, $statement);
        self::assertSame(false, $statement->objects[1]->introduced());
    }
}
