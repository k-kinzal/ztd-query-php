<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Command\InternalCommand;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\DebugRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;

#[CoversClass(DebugRequest::class)]
#[Medium]
final class DebugRequestTest extends TestCase
{
    /**
     * @return iterable<string, array{Scalar, list<int>}>
     */
    public static function providerOperands(): iterable
    {
        yield 'continuing parse error' => [new Collated(new NumberLiteral('1'), new Name('missing')), [1273, 1064]];
        yield 'aborting charset' => [new CharsetConversion(new NumberLiteral('1'), new CharsetName(new Name('missing'))), [1115]];
        yield 'later binding diagnostic' => [new ColumnUse(new Name('absent')), [1064]];
    }

    /**
     * @param list<int> $codes The ordered native conditions
     */
    #[DataProvider('providerOperands')]
    public function testRefusalUsesTypedNoticesBeforeTheDebugBoundary(Scalar $operand, array $codes): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $operation = new Operation($session->semantics()->context([], true, null, $session->resolution([])), new ShowParseTree(new QueryRoot(new Select([], [new SelectExpression($operand)]))));
        $error = (new DebugRequest())->refusal($operation, (new InternalCommand())->error($operation), $session);

        self::assertSame($codes[0], $error->getCode());
        self::assertTrue($error->recorded);
        self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
    }

    public function testRefusalLeavesContextualizationConditionsForTheirOwnStage(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $partitioning = new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause(new \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod(false, new Collated(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new Name('v')), new Name('missing'))), null, null, []);
        $statement = new ShowParseTree(new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')), partitioning: $partitioning));
        $operation = new Operation($session->semantics()->context([], true, null, $session->resolution([])), $statement);

        $error = (new DebugRequest())->refusal($operation, (new InternalCommand())->error($operation), $session);

        self::assertSame(1273, $error->getCode());
        self::assertSame([1273, 1064], array_column($session->diagnostics->conditions, 1));
    }
}
