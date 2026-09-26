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
}
