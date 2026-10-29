<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\Hint\Comment\HintCommentGenerator;

/**
 * Executes accepted hint operands and the warning at which their comment stopped.
 */
#[CoversNothing]
#[Large]
final class HintExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Direct execution reports the model's warning without recovering comment text.
     */
    #[Test]
    public function satisfiesGeneratedHintNoticesBelongToTheirStatement(): void
    {
        $this->forAll(HintCommentGenerator::projections())->then(static function (QueryRoot $statement): void {
            $notice = $statement->notices()[0];
            self::assertInstanceOf(HintError::class, $notice->warning);
            self::assertSame($statement, $notice->subject);
            self::assertSame($notice->warning->offset, $notice->inputSource?->offset);
            $session = (new Instance('8.4.7'))->connect();
            $session->text = 'Generated hint meaning without source SQL';
            $statement = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $statement);
            self::assertCount(1, $statement->notices());
            self::assertTrue($statement->notices()[0]->fromInput);
            self::assertSame($notice->warning, $statement->notices()[0]->warning);
            $reply = (new Execution($session))->statement($statement);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([['1']], $reply->rows);
            self::assertSame(['Warning', 1064, $notice->warning->failure->value], $session->diagnostics->conditions[0]);
            self::assertSame('Generated hint meaning without source SQL', $session->text);
        });
    }
    /**
     * Preparation and execution share semantic slot and hint binding without SQL input.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReleases')]
    public function satisfiesPreparedHintsAndParameterSlotsSurviveRebinding(\SqlSemantics\Contract\GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Literal\ParameterGenerator::preparedDivision($release))->then(
            static function (array $sample) use ($release): void {
                [$statement, $parameters, $expected] = $sample;
                self::assertInstanceOf(QueryRoot::class, $statement);
                $session = (new Instance(substr($release->value, 6)))->connect();
                $session->text = 'Generated preparation without SQL';
                $prepared = \MySqlMemory\Command\Prepared\PreparedCache::capture($statement, $session);
                self::assertIsArray($parameters);
                $bindings = [];
                foreach ($parameters as $parameter) {
                    self::assertIsArray($parameter);
                    self::assertIsInt($parameter[0]);
                    self::assertInstanceOf(\MySqlMemory\Typing\Domain::class, $parameter[1]);
                    $bindings[] = [$parameter[0], $parameter[1]];
                }
                $reply = $prepared->execute($session, 'Generated preparation without SQL', $bindings, \MySqlMemory\Command\Prepared\ParameterBindings::capture($statement));
                self::assertInstanceOf(ResultSet::class, $reply);
                if (is_float($expected)) {
                    self::assertEqualsWithDelta($expected, (float) $reply->rows[0][0], abs($expected) * 1e-14);
                    self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Double, $reply->columns[0]->type);
                } else {
                    self::assertSame([[$expected]], $reply->rows);
                    self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::NewDecimal, $reply->columns[0]->type);
                }
                self::assertSame('quotient', $reply->columns[0]->name);
                self::assertSame(4, $session->variables->read('div_precision_increment'));
                self::assertSame('Generated preparation without SQL', $session->text);
                self::assertSame([], $session->transaction->statements->running);
            }
        );
    }

    /**
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }

}
