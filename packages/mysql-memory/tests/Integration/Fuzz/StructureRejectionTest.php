<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Execution;
use Fuzz\Structure\Profiles;
use Fuzz\Structure\Reference;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversNothing]
#[Large]
final class StructureRejectionTest extends TestCase
{
    public function testNativeRejectionConfirmsTheDirectExecutionError(): void
    {
        $node = new Aggregate(AggregateFunction::Collect, [new NumberLiteral('1')]);
        $profile = (new Profiles())->select($node);
        $failure = null;
        try {
            (new Execution())->run($node, substr($profile->grammar->value, 6));
        } catch (SqlError $error) {
            $failure = $error;
        }
        self::assertInstanceOf(SqlError::class, $failure);
        $observation = (new Reference())->rejection($node, $profile, $failure);

        self::assertTrue($observation['matches']);
        self::assertSame([3037, '22023', 'Invalid GIS data provided to function st_collect.'], $observation['memoryError']);
    }

    public function testNativeSuccessDoesNotConfirmAMissingImplementationError(): void
    {
        $node = new Aggregate(AggregateFunction::Collect, [new NullLiteral()]);
        $profile = (new Profiles())->select($node);
        $failure = StatementError::NotSupportedYet->error('the type of Aggregate');
        $observation = (new Reference())->rejection($node, $profile, $failure);

        self::assertFalse($observation['matches']);
        self::assertSame('SELECT ST_COLLECT(NULL)', $observation['sql']);
    }
    public function testNativeRejectionUsesTheRuntimeModeBeyondLexicalFlags(): void
    {
        $node = new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Date, '0000-00-00');
        $profile = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.4.7'))->profile();
        $failure = null;
        try {
            (new Execution())->run($node, '8.4.7', mode: 'NO_ZERO_DATE');
        } catch (SqlError $error) {
            $failure = $error;
        }
        self::assertInstanceOf(SqlError::class, $failure);
        $observation = (new Reference())->rejection($node, $profile, $failure, sqlMode: 'NO_ZERO_DATE');

        self::assertTrue($observation['matches']);
        self::assertSame('NO_ZERO_DATE', $observation['mode']);
    }

}
