<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generators;
use Eris\TestTrait;
use LogicException;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Call\Temporal\DateArithmeticGenerator;
use Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator;

/**
 * Executes generated interval arithmetic through the current query execution boundary.
 */
#[CoversNothing]
#[Large]
final class IntervalExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Equivalent unit keywords give identical rows and protocol type metadata.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesUnitAliasesPreserveValuesAndResultTypes(GrammarRelease $release): void
    {
        $queries = Generators::map(static function (DateArithmetic $expression): QueryRoot {
            if (!$expression->date instanceof StringLiteral || !$expression->quantity instanceof StringLiteral) {
                throw new LogicException('The arithmetic generator supplies literal operands.');
            }
            $date = new StringLiteral($expression->date->segments, collation: $expression->date->collation);
            $quantity = new StringLiteral($expression->quantity->segments, collation: $expression->quantity->collation);
            return new QueryRoot(new Select([], [
                new SelectExpression($expression, new Name('written')),
                new SelectExpression(new DateArithmetic($expression->subtract, $date, $quantity, $expression->unit->canonical()), new Name('canonical')),
            ]));
        }, new DateArithmeticGenerator());
        $this->forAll(new AnalyzedStatementGenerator($release, $queries))->withMaxSize(10)->then(static function (MySqlStatement $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6), ['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_unicode_ci'], ['pbt']))->connect(database: 'pbt');
            $session->text = 'not SQL: generated interval execution';
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertCount(1, $reply->rows);
            self::assertCount(2, $reply->rows[0]);
            self::assertNotNull($reply->rows[0][0]);
            self::assertSame($reply->rows[0][0], $reply->rows[0][1]);
            self::assertEquals($statement->statementInformation()->field(0)->type, $statement->statementInformation()->field(1)->type);
            self::assertCount(2, $reply->columns);
            [$written, $canonical] = $reply->columns;
            self::assertSame([$written->type, $written->length, $written->decimals, $written->flags, $written->charset], [$canonical->type, $canonical->length, $canonical->decimals, $canonical->flags, $canonical->charset]);
            self::assertSame(0, $reply->warnings());
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }
}
