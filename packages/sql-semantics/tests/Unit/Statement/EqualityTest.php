<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Comments;
use SqlSemantics\Statement\Equality;
use SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149 as Commit;
use SqlSemantics\Statement\Model\Sqlite\Value\TransOptWith_6ac05548 as NoTransaction;
use SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324 as Transaction;

#[CoversClass(Equality::class)]
#[UsesClass(Comments::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(\SqlSemantics\Core\Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[Medium]
final class EqualityTest extends TestCase
{
    public function testSameAnswersTrueForValuesOfTheSameFormSpellingsAndOptions(): void
    {
        self::assertTrue(Equality::same(new Commit('COMMIT', new Transaction()), new Commit('COMMIT', new Transaction())));
        $commit = new Commit('COMMIT', new Transaction());
        self::assertTrue(Equality::same($commit, $commit));
    }

    public function testSameAnswersFalseWhenASpellingAChildFormOrACommentDiffers(): void
    {
        self::assertFalse(Equality::same(new Commit('COMMIT', new Transaction()), new Commit('END', new Transaction())));
        self::assertFalse(Equality::same(new Commit('COMMIT', new Transaction()), new Commit('commit', new Transaction())));
        self::assertFalse(Equality::same(new Commit('COMMIT', new Transaction()), new Commit('COMMIT', new NoTransaction())));
        self::assertFalse(Equality::same(new Commit('COMMIT', new Transaction()), new Commit('COMMIT', new Transaction(), new Comments([1 => ['-- x']]))));
        self::assertFalse(Equality::same(new Commit('COMMIT', new Transaction()), new Transaction()));
    }

    public function testSameComparesNumericLookingSpellingsExactly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        self::assertFalse(Equality::same($semantics->analyze('SELECT 1e1')->command, $semantics->analyze('SELECT 10')->command));
        self::assertFalse(Equality::same($semantics->analyze('SELECT 0x10')->command, $semantics->analyze('SELECT 16')->command));
        self::assertTrue(Equality::same($semantics->analyze('SELECT 1e1')->command, $semantics->analyze('select 1e1')->command));
    }
}
