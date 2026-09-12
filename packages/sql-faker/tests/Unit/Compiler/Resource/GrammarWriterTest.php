<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Compiler\Resource\ArtifactDirectory;
use SqlFaker\Compiler\Resource\GrammarWriter;
use SqlFaker\Grammar\Resource\SqlVersion;

#[CoversClass(GrammarWriter::class)]
#[UsesClass(ArtifactDirectory::class)]
#[UsesClass(SqlVersion::class)]
final class GrammarWriterTest extends TestCase
{
    public function testPublishReplacesTheCompleteAstAndCleansItsStagingFile(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-ast-' . bin2hex(random_bytes(8));
        $path = $directory . '/ast.php';
        $version = new SqlVersion('mysql', 'mysql-8.4.7', $path);
        try {
            $writer = new GrammarWriter();
            $writer->publish($version, '<?php return "old";');
            $writer->publish($version, '<?php return "new";');
            self::assertSame('<?php return "new";', file_get_contents($path));
            self::assertSame(['.', '..', 'ast.php'], scandir($directory));
        } finally {
            unlink($path);
            rmdir($directory);
        }
    }

    public function testPublishFailsBeforeStagingWhenTheParentIsAFile(): void
    {
        $parent = tempnam(sys_get_temp_dir(), 'sql-faker-parent-');
        self::assertNotFalse($parent);
        try {
            $this->expectException(RuntimeException::class);
            (new GrammarWriter())->publish(new SqlVersion('mysql', 'mysql-8.4.7', $parent . '/ast.php'), 'ast');
        } finally {
            unlink($parent);
        }
    }
}
