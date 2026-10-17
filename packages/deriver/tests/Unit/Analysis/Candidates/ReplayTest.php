<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Candidates;

use Deriver\Analysis\Candidates\Replay;
use Deriver\Analyzer;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ReplayTest extends TestCase
{
    /**
     * @throws JsonException If the export cannot be encoded
     */
    public function testVerifyStillRevalidatesThroughTheFormerName(): void
    {
        $input = new ProjectInput([new SourceFile('a.php', '<?php function f(){return 42;}')]);
        $query = new ReturnQuery('f');
        $json = (new Analyzer())->open($input)->derive($query)->toJson();
        $replay = new Replay();
        $replay->verify($json, $input, new Configuration(), $query);
        self::assertJson($json);
    }
}
