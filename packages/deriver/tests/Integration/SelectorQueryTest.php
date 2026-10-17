<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Analyzer;
use Deriver\Exception\InvalidInputException;
use Deriver\Finder;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Query;
use Deriver\Replay;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SourceDirectory;

#[CoversNothing]
final class SelectorQueryTest extends TestCase
{
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testAnalyzeSelectsNamespacedReturnsAndFormalParameterOrigins(): void
    {
        $source = new SourceDirectory();
        $path = $source->write('app.php', '<?php namespace App; class Repository { public static function find($id){return $id * 2;} } Repository::find(3); Repository::find(5);');
        $analyzer = new Analyzer();
        $parameters = $analyzer->analyze([$path], 'App\\Repository::find#parameter(id)');
        $returns = $analyzer->analyze([$path], 'App\\Repository::find#return');
        self::assertSame([3, 5], array_column($parameters->candidates, 'result'));
        self::assertSame([6, 10], array_column($returns->candidates, 'result'));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testAnalyzeSelectsArgumentExpressionsWithoutExecutingTheCall(): void
    {
        $source = new SourceDirectory();
        $path = $source->write('source #1.php', "<?php\n\$x = 3;\nunknown(\$x++, \$x);\n");
        $files = (new Finder())->in($source->path)->include('**/*.php')->files();
        $analyzer = new Analyzer();
        self::assertSame(3, $analyzer->analyze($files, $path . ':3:1#argument(0)')->candidates[0]->result);
        self::assertSame(4, $analyzer->analyze($files, $path . ':3:1#argument(1)')->candidates[0]->result);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testAnalyzeResolvesRelativePathsAgainstTheWorkingDirectory(): void
    {
        $source = new SourceDirectory();
        $path = $source->write('src/a.php', "<?php\nunknown(sql: 'SELECT 1');\n");
        $cwd = getcwd();
        self::assertNotFalse($cwd);
        self::assertTrue(chdir($source->path));
        try {
            $query = Query::parse('src/./a.php:2:1#argument(sql)');
            self::assertSame('SELECT 1', (new Analyzer())->analyze([$path], $query->toString())->candidates[0]->result);
            self::assertSame('SELECT 1', (new Analyzer())->analyze(['src/a.php'], $path . ':2:1#argument(0)')->candidates[0]->result);
        } finally {
            chdir($cwd);
        }
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testAnalyzeFindsArgumentsInsideClosures(): void
    {
        $source = new SourceDirectory();
        $path = $source->write('a.php', "<?php\n\$run = function () {\n    unknown('inside');\n};\n");
        self::assertSame('inside', (new Analyzer())->analyze([$path], $path . ':3:5#argument(0)')->candidates[0]->result);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testAnalyzeExportsEvidenceThatCanBeReplayedWithTheSameSelector(): void
    {
        $source = new SourceDirectory();
        $path = $source->write('a.php', "<?php\nunknown('sql');\n");
        $query = $path . ':2:1#argument(0)';
        $result = (new Analyzer())->analyze([$path], $query);
        (new Replay())->verify($result->toJson(), ProjectInput::fromFiles([$path]), new Configuration(), $query);
        self::assertSame('sql', $result->candidates[0]->result);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testAnalyzeUsesByteColumnsWithUtf8AndCrLfSource(): void
    {
        $source = new SourceDirectory();
        $prefix = '$label = "日本語"; ';
        $path = $source->write('a.php', "<?php\r\n" . $prefix . "unknown('sql');\r\n");
        $query = $path . ':2:' . (strlen($prefix) + 1) . '#argument(0)';
        self::assertSame('sql', (new Analyzer())->analyze([$path], $query)->candidates[0]->result);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testAnalyzeRejectsCallsSharingTheSameStartPosition(): void
    {
        $source = new SourceDirectory();
        $path = $source->write('a.php', "<?php\nfactory()->send('sql');\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('exactly one captured call');
        (new Analyzer())->analyze([$path], $path . ':2:1#argument(0)');
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testAnalyzeDoesNotAddAFileNamedOnlyByTheSelector(): void
    {
        $source = new SourceDirectory();
        $selected = $source->write('selected.php', '<?php unknown(1);');
        $excluded = $source->write('excluded.php', '<?php unknown(2);');
        $this->expectException(InvalidInputException::class);
        (new Analyzer())->analyze([$selected], $excluded . ':1:7#argument(0)');
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testAnalyzeRejectsAnUnknownFormalParameter(): void
    {
        $source = new SourceDirectory();
        $path = $source->write('a.php', '<?php function find($id){return $id;}');
        $this->expectException(InvalidInputException::class);
        (new Analyzer())->analyze([$path], 'find#parameter(missing)');
    }
}
