<?php

declare(strict_types=1);

namespace Tests\Unit\Project;

use Deriver\Project\SourceFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceFile::class)]
#[Small]
final class SourceFileTest extends TestCase
{
    public function testRetainsCapturedBytesAndDeclarationAvailability(): void
    {
        $file = new SourceFile('src/Library.php', "<?php // opaque bytes \xff", true);
        self::assertSame('src/Library.php', $file->path);
        self::assertSame("<?php // opaque bytes \xff", $file->contents);
        self::assertTrue($file->declarationsOnly);
        self::assertFalse((new SourceFile('empty.php', ''))->declarationsOnly);
    }
}
