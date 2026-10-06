<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Candidates;

use Deriver\Analysis\Candidates\Replay as Subject;
use Deriver\Project\Configuration;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ReplayTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testVerifyDetectsChangedValues(): void
    {
        $input = new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('a.php', '<?php function target(){return 42;}')]);
        $config = new Configuration();
        $query = new ReturnQuery('target');
        $result = (new \Deriver\Analyzer())->open($input, $config)->derive($query);
        (new Subject())->verify($result->toJson(), $input, $config, $query);
        $this->expectException(\Deriver\Exception\InvalidInputException::class);
        (new Subject())->verify('[]', $input, $config, $query);
    }

}
