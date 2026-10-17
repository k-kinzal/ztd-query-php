<?php

declare(strict_types=1);

namespace Tests\Unit\Project;

use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CapturedProjectTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCallsToListsTheCapturedCallSites(): void
    {
        self::assertSame('f', (new Analyzer())->open(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]))->callsTo('f')[0]->target);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeclarationsExposeTheCapturedSymbols(): void
    {
        self::assertContains('f', (new Analyzer())->open(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]))->declarations()->symbols());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEntrypointsAreEmptyWithoutProviders(): void
    {
        self::assertSame([], (new Analyzer())->open(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]))->entrypoints());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotNamesTheCapturedFiles(): void
    {
        self::assertSame(['a.php'], array_keys((new Analyzer())->open(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]))->snapshot()->sources));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCommentsReadTheDocblocksOfADeclaration(): void
    {
        self::assertSame([], (new Analyzer())->open(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]))->comments('f'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExpressionSelectsAnExactByteRange(): void
    {
        $project = (new Analyzer())->open(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]));
        $site = $project->callsTo('f')[0];
        self::assertSame($site->argument(0)->register, $project->expression('a.php', $site->argument(0)->source->start, $site->argument(0)->source->end)->register);
    }

}
