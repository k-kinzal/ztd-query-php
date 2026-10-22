<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Rejections;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

/**
 * Keeps the construction campaign's runtime exceptions narrower than all SQL failures.
 */
#[CoversNothing]
#[Small]
final class RejectionsTest extends TestCase
{
    /**
     * State conditions pass independently of the SQLSTATE shared with structural failures.
     */
    public function testOnlyAnExplicitStateDependencyExplainsARejection(): void
    {
        $policy = new Rejections();
        $node = new NumberLiteral('1');
        self::assertSame('current database', $policy->reason(QueryError::NoDatabase->error(), $node));
        foreach ([StatementError::ParseError, StatementError::UnknownError, StatementError::NotSupportedYet, StatementError::WrongArguments] as $error) {
            self::assertNull($policy->reason(new SqlError($error, 'test condition'), $node));
        }
    }
}
