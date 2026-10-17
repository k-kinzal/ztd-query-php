<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Selection;

use Deriver\Analysis\Selection\SelectorResolution;
use Deriver\Exception\InvalidInputException;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query;
use Deriver\Query\ParameterQuery;
use Deriver\Query\ReturnQuery;
use Deriver\Session\Session;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SelectorResolutionTest extends TestCase
{
    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testResolveBindsSymbolSelectors(): void
    {
        $session = new Session(new ProjectInput([new SourceFile('a.php', '<?php function f($id){return $id;}')]), new Configuration());
        $resolver = new SelectorResolution($session->program);
        self::assertEquals(new ReturnQuery('f'), $resolver->resolve(Query::parse('f#return')));
        self::assertEquals(new ParameterQuery('f', 'id'), $resolver->resolve(Query::parse('f#parameter(id)')));
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testCallAtSelectsAnExactCallAmongCallsOnTheSameLine(): void
    {
        $session = new Session(new ProjectInput([new SourceFile('a.php', '<?php sink(1); sink(2);')]), new Configuration());
        $call = (new SelectorResolution($session->program))->callAt(Query::parse('a.php:1:16#argument(0)'));
        self::assertSame(15, $call->source->start);
        self::assertSame('sink', $call->target);
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    #[DataProvider('providerInvalidSelections')]
    public function testResolveRejectsUnsupportedOrMissingTargets(string $query): void
    {
        $session = new Session(new ProjectInput([new SourceFile('a.php', '<?php function f($id){return $id;} sink(1);')]), new Configuration());
        $this->expectException(InvalidInputException::class);
        (new SelectorResolution($session->program))->resolve(Query::parse($query));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidSelections(): iterable
    {
        foreach (['missing#return', 'f#return(id)', 'f#parameter', 'f#parameter(0)', 'f#parameter(id,extra)', 'f#unknown', 'f#argument(0)', 'a.php:1:1#return', 'a.php:1:1#argument', 'a.php:1:1#argument(0)', 'missing.php:1:34#argument(0)', 'a.php:1:36#argument(1)'] as $query) {
            yield $query => [$query];
        }
    }
}
