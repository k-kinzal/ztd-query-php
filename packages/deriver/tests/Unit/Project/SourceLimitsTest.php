<?php

declare(strict_types=1);

namespace Tests\Unit\Project;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Project\SourceLimits
 */
#[CoversClass(SourceLimits::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[Small]
final class SourceLimitsTest extends TestCase
{
    public function testCheckRejectsTotalBytesBeforeParsing(): void
    {
        $this->expectException(InvalidInputException::class);
        (new SourceLimits(bytes: 10))->check(new ProjectInput([new SourceFile('a.php', '<?php 1;'),new SourceFile('b.php', '<?php 2;')]));
    }
    public function testCheckRejectsAnOversizedIndividualFile(): void
    {
        $this->expectException(InvalidInputException::class);
        (new SourceLimits(fileBytes: 3))->check(new ProjectInput([new SourceFile('a.php', '<?php 1;')]));
    }
    public function testCheckRejectsTooManyFiles(): void
    {
        $this->expectException(InvalidInputException::class);
        (new SourceLimits(files: 1))->check(new ProjectInput([new SourceFile('a.php', ''),new SourceFile('b.php', '')]));
    }
    public function testCheckAcceptsExactAdmissionBounds(): void
    {
        $input = new ProjectInput([new SourceFile('a.php', '<?php 1;')]);
        (new SourceLimits(files: 1, bytes: 8, fileBytes: 8))->check($input);
        self::assertCount(1, $input->files);
    }
    public function testCheckRejectsNonPositivePolicyLimits(): void
    {
        $this->expectException(InvalidInputException::class);
        new SourceLimits(depth: 0);
    }

    public function testDefaultsBoundCapturedFilesBytesAndSyntaxBeforeAnalysis(): void
    {
        $limits = new SourceLimits();
        self::assertSame(10000, $limits->files);
        self::assertSame(67108864, $limits->bytes);
        self::assertSame(4194304, $limits->fileBytes);
        self::assertSame(250000, $limits->nodes);
        self::assertSame(128, $limits->depth);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidAdmissionLimits')]
    public function testCheckRejectsEveryNonpositiveAdmissionDimension(int $files, int $bytes, int $fileBytes, int $nodes, int $depth): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Source limits must be positive.');
        new SourceLimits($files, $bytes, $fileBytes, $nodes, $depth);
    }

    /**
     * @return iterable<string,array{int,int,int,int,int}>
     */
    public static function providerInvalidAdmissionLimits(): iterable
    {
        yield 'zero files' => [0,1,1,1,1];
        yield 'zero bytes' => [1,0,1,1,1];
        yield 'zero file bytes' => [1,1,0,1,1];
        yield 'zero nodes' => [1,1,1,0,1];
        yield 'zero depth' => [1,1,1,1,0];
        yield 'negative files' => [-1,1,1,1,1];
        yield 'negative bytes' => [1,-1,1,1,1];
        yield 'negative file bytes' => [1,1,-1,1,1];
        yield 'negative nodes' => [1,1,1,-1,1];
        yield 'negative depth' => [1,1,1,1,-1];
    }

    public function testCheckAcceptsExactCombinedBytesAcrossSeveralFiles(): void
    {
        $input = new ProjectInput([new SourceFile('a.php', '123'),new SourceFile('b.php', '12')]);
        $limits = new SourceLimits(files:2, bytes:5, fileBytes:3, nodes:1, depth:1);
        $limits->check($input);
        self::assertSame(5, $limits->bytes);
        self::assertCount(2, $input->files);
    }
}
