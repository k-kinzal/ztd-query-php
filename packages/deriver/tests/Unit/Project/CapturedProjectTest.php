<?php

declare(strict_types=1);

namespace Tests\Unit\Project;

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
        self::assertSame('f', (new \Deriver\Session\Session(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]), new \Deriver\Project\Configuration()))->callsTo('f')[0]->target);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeclarationsExposeTheCapturedSymbols(): void
    {
        self::assertContains('f', (new \Deriver\Session\Session(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]), new \Deriver\Project\Configuration()))->declarations()->symbols());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEntrypointsAreEmptyWithoutProviders(): void
    {
        self::assertSame([], (new \Deriver\Session\Session(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]), new \Deriver\Project\Configuration()))->entrypoints());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSnapshotNamesTheCapturedFiles(): void
    {
        self::assertSame(['a.php'], array_keys((new \Deriver\Session\Session(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]), new \Deriver\Project\Configuration()))->snapshot()->sources));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCommentsReadTheDocblocksOfADeclaration(): void
    {
        self::assertSame([], (new \Deriver\Session\Session(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]), new \Deriver\Project\Configuration()))->comments('f'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExpressionSelectsAnExactByteRange(): void
    {
        $project = (new \Deriver\Session\Session(new ProjectInput([new SourceFile('a.php', '<?php function f($x){} function g(){f(1);}')]), new \Deriver\Project\Configuration()));
        $site = $project->callsTo('f')[0];
        self::assertSame($site->argument(0)->register, $project->expression('a.php', $site->argument(0)->source->start, $site->argument(0)->source->end)->register);
    }

}
