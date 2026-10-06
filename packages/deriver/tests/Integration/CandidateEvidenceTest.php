<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class CandidateEvidenceTest extends TestCase
{
    /**
     * @return array<string, array{string, int}> Distinct outer caller selections
     */
    public static function providerCallers(): array
    {
        return ['first' => ['controllerA',35], 'second' => ['controllerB',65]];
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    #[DataProvider('providerCallers')]
    public function testE01E02OuterCallersDisambiguateAnIdenticalInnerSite(string $caller, int $expected): void
    {
        $source = '<?php function ttl(int $n){return $n+5;} function recipe(int $n){return ttl($n);} function controllerA(){return recipe(30);} function controllerB(){return recipe(60);}';
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('calls.php', $source)]));
        $result = $session->derive(new ReturnQuery('ttl'));
        self::assertSame([35, 65], array_map(static fn ($candidate) => $candidate->result, $result->candidates));

        $selected = $result->forCaller($caller);
        self::assertCount(1, $selected);
        self::assertSame($expected, $selected->candidates[0]->result);
        $kinds = array_column($selected->candidates[0]->evidence[0]->nodes(), 'kind');
        self::assertContains('argument-binding', $kinds);
        self::assertContains('source-definition', $kinds);
        self::assertCount(0, $result->forCaller('absent'));
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function testE03EqualValuesKeepBothCallers(): void
    {
        $source = '<?php function ttl($n){return $n+5;}function a(){return ttl(30);}function b(){return ttl(30);}';
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('calls.php', $source)]));
        $result = $session->derive(new ReturnQuery('ttl'));
        self::assertCount(1, $result);
        self::assertCount(2, $result->candidates[0]->evidence);
        self::assertCount(1, $result->forCaller('a'));
        self::assertCount(1, $result->forCaller('b'));
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function testE07E08WarmDependencyEvidenceSurvivesSessionRelease(): void
    {
        $source = '<?php function f($n){$x=5+$n;observe($x);return $x;}function caller(){return f(30);}';
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('calls.php', $source)]));
        $query = new ReturnQuery('f');
        $cold = $session->derive($query)->toJson();
        $session->release();
        $start = strrpos($source, '$x');
        self::assertNotFalse($start);
        $session->derive(new ValueQuery($session->expression('calls.php', $start, $start + 2)));
        $warm = $session->derive($query);
        self::assertGreaterThan(0, $warm->statistics->sharedNodeHits);
        self::assertSame($cold, $warm->toJson());
        $session->release();
        unset($session);
        self::assertSame(hash('sha256', $cold), hash('sha256', $warm->toJson()));
    }

    /**
     * @throws JsonException If fixture or captured metadata cannot be encoded
     */
    public function testE09LiteralHasExactUtf8AndCrlfSourceCoordinates(): void
    {
        $source = "<?php\r\nfunction f(){ /* 日本語 */ observe(42);observe(65);}\r\n";
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('unicode.php', $source)]));
        $start = strpos($source, '65');
        self::assertNotFalse($start);
        $result = $session->derive(new ValueQuery($session->expression('unicode.php', $start, $start + 2)));
        self::assertSame(65, $result->candidates[0]->result);
        $literals = array_values(array_filter($result->candidates[0]->evidence[0]->nodes(), static fn ($node): bool => $node->kind === 'source-definition'));
        self::assertCount(1, $literals);
        self::assertNotNull($literals[0]->source);
        self::assertSame([$start, $start + 2, 2, $start - 6, 2, $start - 4], [$literals[0]->source->start, $literals[0]->source->end, $literals[0]->source->line, $literals[0]->source->column, $literals[0]->source->endLine, $literals[0]->source->endColumn]);
    }
}
