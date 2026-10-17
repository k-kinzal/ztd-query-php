<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Candidates;

use Deriver\Analysis\Candidates\ObservationRoot;
use Deriver\Candidate\Index\DefinitionIndex;
use Deriver\Candidate\Target;
use Deriver\Query\ReturnQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ObservationRootTest extends TestCase
{
    /**
     * @throws JsonException If query metadata cannot be encoded
     */
    public function testOfIdentifiesTheTargetAboveTheValueForest(): void
    {
        $session = new \Deriver\Session\Session(new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('candidate.php', '<?php ' . 'function target(){return 1;}')]), new \Deriver\Project\Configuration());
        $query = new ReturnQuery('target');
        $root = (new ObservationRoot())->of(new Target($query, 'target', 'return', 'target'), Term::constant(1), new DefinitionIndex($session->program));
        self::assertSame('observation', $root->kind);
        self::assertSame('target', $root->attributes['owner']);
        self::assertSame('target', $root->attributes['target']);
        self::assertSame(ReturnQuery::class, $root->attributes['role']);
        self::assertSame('candidate.php', $root->source?->path);
        self::assertArrayHasKey('value', $root->inputs);
    }
}
