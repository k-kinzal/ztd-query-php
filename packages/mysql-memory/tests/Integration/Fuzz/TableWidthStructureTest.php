<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversNothing]
#[Large]
final class TableWidthStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, int, bool}>
     */
    public static function providerWidths(): iterable
    {
        foreach ([GrammarRelease::MySql8044, GrammarRelease::MySql847] as $release) {
            foreach (range(1, 4) as $width) {
                yield $release->value . ':' . $width => [$release, $width, false];
            }
            yield $release->value . ':fulltext' => [$release, 1, true];
        }
    }

    #[DataProvider('providerWidths')]
    public function testMissingTablesAcquireTheOriginalPredicateWidth(GrammarRelease $release, int $width, bool $fulltext): void
    {
        $columns = array_map(static fn (int $index): M\Name\ColumnUse => new M\Name\ColumnUse(new Name('c' . $index)), range(1, max(2, $width)));
        $operand = $width === 1 ? new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, $columns[0], $columns[1]) : new M\Expression\Row($columns);
        $operand = $fulltext ? new M\Expression\Access\FullTextSearch($columns, new M\Literal\NullLiteral()) : $operand;
        $query = new M\Query\ExplicitTable(new QualifiedName(new Name('t')));
        $predicate = new M\Expression\Subquery\InQuery($operand, $query);
        $result = (new Comparison())->compare($predicate, new LanguageProfile($release));
        self::assertTrue($result['matches'], serialize($result));
        $history = $result['transitions'];
        self::assertNotEmpty($history);
        $last = $history[count($history) - 1];
        self::assertSame($result['sql'], $last['sql']);
        self::assertArrayNotHasKey('error', $last['native'], serialize($history));
        self::assertIsArray($last['native']['results']);
        self::assertIsArray($last['native']['results'][0]);
        self::assertSame([[$width === 1 ? '0' : '1']], $last['native']['results'][0]['rows']);
        self::assertSame($operand, $predicate->operand);
        self::assertSame($query, $predicate->query);
    }
}
