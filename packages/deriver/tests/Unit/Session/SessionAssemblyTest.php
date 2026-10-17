<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Session\SessionAssembly;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SessionAssemblyTest extends TestCase
{
    /**
     * @throws JsonException If the snapshot identity cannot be encoded
     */
    public function testAssemblyRunsTheProjectStagesWithoutLoweringAnyBody(): void
    {
        $assembly = new SessionAssembly(new ProjectInput([new SourceFile('a.php', '<?php function a(){return b();} function b(){return 1;}')]), new Configuration());
        self::assertSame(0, $assembly->program->graphCount());
        self::assertSame(['a', 'b', 'script:a.php'], $assembly->program->symbols());
        self::assertSame($assembly->identity->id, $assembly->snapshot->id);
        self::assertSame($assembly->program->snapshotId, $assembly->snapshot->id);
        self::assertSame($assembly->program, $assembly->index->program);
        self::assertSame(0, $assembly->cache->count());
    }

    /**
     * @throws JsonException If the snapshot identity cannot be encoded
     */
    public function testAssemblyIsDeterministicAcrossSessions(): void
    {
        $input = new ProjectInput([new SourceFile('a.php', '<?php function a(){return 1;}')]);
        $first = new SessionAssembly($input, new Configuration());
        $second = new SessionAssembly($input, new Configuration());
        self::assertSame($first->snapshot->id, $second->snapshot->id);
        self::assertSame($first->snapshot->models, $second->snapshot->models);
        self::assertSame(serialize($first->program->symbols()), serialize($second->program->symbols()));
    }

    /**
     * @throws JsonException If the snapshot identity cannot be encoded
     */
    public function testValidateSourceRejectsMalformedPhpOnlyWhenRequested(): void
    {
        $input = new ProjectInput([new SourceFile('a.php', '<?php function {')]);
        $tolerant = new SessionAssembly($input, new Configuration(), validateSource: false);
        self::assertSame('INCOMPLETE_SOURCE', $tolerant->snapshot->diagnostics[0]->code);
        $this->expectException(InvalidInputException::class);
        new SessionAssembly($input, new Configuration());
    }
}
