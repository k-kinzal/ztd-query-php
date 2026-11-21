<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Statement\Dml\FileFormat;

#[CoversNothing]
#[Small]
final class FileFormatTest extends TestCase
{
    public function testAFileFormatIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(FileFormat::class));
        self::assertContains(Node::class, class_implements(FileFormat::class));
    }
}
