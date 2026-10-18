<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Sources;

use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Source::class)]
#[Small]
final class SourceTest extends TestCase
{
    public function testRetainsCapturedBytesAndDeclarationAvailability(): void
    {
        $source = new Source('src/Library.php', "<?php // opaque bytes \xff", true);
        self::assertSame('src/Library.php', $source->path);
        self::assertSame("<?php // opaque bytes \xff", $source->contents);
        self::assertTrue($source->declarationsOnly);
        self::assertFalse((new Source('empty.php', ''))->declarationsOnly);
    }
}
