<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Source\SourceFile;

#[CoversClass(SourceFile::class)]
final class SourceFileTest extends TestCase
{
    public function testKeepsThePathAndTheSource(): void
    {
        $file = new SourceFile('src/a.php', '<?php');
        self::assertSame('src/a.php', $file->path);
        self::assertSame('<?php', $file->code);
    }
}
