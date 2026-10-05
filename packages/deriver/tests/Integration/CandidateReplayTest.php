<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Analysis\Candidates\Replay;
use Deriver\Analyzer;
use Deriver\Exception\InvalidInputException;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class CandidateReplayTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerMutations(): iterable
    {
        yield 'missing caller' => ['caller'];
        yield 'missing conditional branch' => ['candidate'];
        yield 'changed file hash' => ['hash'];
        yield 'dangling root' => ['edge'];
        yield 'changed model manifest' => ['model'];
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    #[DataProvider('providerMutations')]
    public function testOriginAndEvidenceMutationsAreDetected(string $mutation): void
    {
        $input = new ProjectInput([new SourceFile('replay.php', '<?php function f($x){return $x?30:60;}function a(){return f(true);}function b(){return f(true);}function c(){return f(false);}')]);
        $configuration = new Configuration();
        $query = new ReturnQuery('f');
        $result = (new Analyzer())->open($input, $configuration)->derive($query);
        $json = $result->toJson();
        (new Replay())->verify($json, $input, $configuration, $query);
        $mutated = (new \Tests\Fake\ReplayMutations())->apply($result, $mutation);
        $this->expectException(InvalidInputException::class);
        (new Replay())->verify($mutated, $input, $configuration, $query);
    }
}
