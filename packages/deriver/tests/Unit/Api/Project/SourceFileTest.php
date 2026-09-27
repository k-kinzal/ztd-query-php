<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Project;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Api\Project\SourceFile::class)]
#[Small]
final class SourceFileTest extends TestCase
{
    public function testRetainsCapturedBytesAndDeclarationAvailability(): void
    {
        $file = new \Deriver\Api\Project\SourceFile('src/Library.php', "<?php // opaque bytes \xff", true);
        self::assertSame('src/Library.php', $file->path);
        self::assertSame("<?php // opaque bytes \xff", $file->contents);
        self::assertTrue($file->declarationsOnly);
        self::assertFalse((new \Deriver\Api\Project\SourceFile('empty.php', ''))->declarationsOnly);
    }
}
