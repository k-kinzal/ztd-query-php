<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\TestTrait;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
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
}
