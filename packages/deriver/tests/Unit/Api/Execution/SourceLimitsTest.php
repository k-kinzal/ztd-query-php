<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Api\Execution\SourceLimits
 */
#[CoversClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\InvalidInputException::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[Small]
final class SourceLimitsTest extends TestCase
{
    public function testCheckRejectsTotalBytesBeforeParsing(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Api\Execution\SourceLimits(bytes: 10))->check(new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', '<?php 1;'),new \Deriver\Api\Project\SourceFile('b.php', '<?php 2;')]));
    }
    public function testCheckRejectsAnOversizedIndividualFile(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Api\Execution\SourceLimits(fileBytes: 3))->check(new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', '<?php 1;')]));
    }
    public function testCheckRejectsTooManyFiles(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        (new \Deriver\Api\Execution\SourceLimits(files: 1))->check(new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', ''),new \Deriver\Api\Project\SourceFile('b.php', '')]));
    }
    public function testCheckAcceptsExactAdmissionBounds(): void
    {
        $input = new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', '<?php 1;')]);
        (new \Deriver\Api\Execution\SourceLimits(files: 1, bytes: 8, fileBytes: 8))->check($input);
        self::assertCount(1, $input->files);
    }
    public function testCheckRejectsNonPositivePolicyLimits(): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        new \Deriver\Api\Execution\SourceLimits(depth: 0);
    }

    public function testDefaultsBoundCapturedFilesBytesAndSyntaxBeforeAnalysis(): void
    {
        $limits = new \Deriver\Api\Execution\SourceLimits();
        self::assertSame(10000, $limits->files);
        self::assertSame(67108864, $limits->bytes);
        self::assertSame(4194304, $limits->fileBytes);
        self::assertSame(250000, $limits->nodes);
        self::assertSame(128, $limits->depth);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidAdmissionLimits')]
    public function testCheckRejectsEveryNonpositiveAdmissionDimension(int $files, int $bytes, int $fileBytes, int $nodes, int $depth): void
    {
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('Source limits must be positive.');
        new \Deriver\Api\Execution\SourceLimits($files, $bytes, $fileBytes, $nodes, $depth);
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
        $input = new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('a.php', '123'),new \Deriver\Api\Project\SourceFile('b.php', '12')]);
        $limits = new \Deriver\Api\Execution\SourceLimits(files:2, bytes:5, fileBytes:3, nodes:1, depth:1);
        $limits->check($input);
        self::assertSame(5, $limits->bytes);
        self::assertCount(2, $input->files);
    }
}
