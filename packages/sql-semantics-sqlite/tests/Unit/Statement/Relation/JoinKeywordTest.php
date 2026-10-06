<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword;

#[CoversClass(JoinKeyword::class)]
#[Medium]
final class JoinKeywordTest extends TestCase
{
    public function testCasesSpellTheJoinWordsSqliteKnows(): void
    {
        self::assertSame(['NATURAL', 'LEFT', 'OUTER', 'RIGHT', 'FULL', 'INNER', 'CROSS'], array_map(static fn (JoinKeyword $keyword): string => $keyword->value, JoinKeyword::cases()));
        self::assertSame(JoinKeyword::Full, JoinKeyword::from('FULL'));
    }

    public function testCasesAreReadFromTheWordsBeforeJoinWhateverTheirSpelling(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t natural Left outer JOIN u');

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(JoinChain::class, $query->statement->from);
        self::assertSame([JoinKeyword::Natural, JoinKeyword::Left, JoinKeyword::Outer], $query->statement->from->steps[0]->operator->words);
        self::assertSame('SELECT * FROM t NATURAL LEFT OUTER JOIN u', $query->toString());
    }
}
