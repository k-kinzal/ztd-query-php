<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use Tests\Fake\MemoryClient as Execution;

/**
 * Typed hints determine expression domains without input text and restore session values.
 */
#[CoversNothing]
#[Medium]
final class TypedHintsProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerPrecisions(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ([0, 1, 4, 10, 30] as $precision) {
                yield $release . ':' . $precision => [$release, $precision];
            }
        }
    }

    /**
     * Checks decimal values and metadata for each constructed precision and release.
     */
    #[DataProvider('providerPrecisions')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesOperationAndExecutionHonorTypedHintsBeforeDerivingDomains(string $release, int $precision): void
    {
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $query = new QueryRoot(new Select([], [new SelectExpression(new Arithmetic(ArithmeticOperator::Divide, new NumberLiteral('1'), new NumberLiteral('3')))], hints: [new VariableHint('div_precision_increment', new HintLiteral(HintLiteralKind::Integer, (string) $precision))]));
        $operation = (new Execution())->operation($session, $query, []);
        $reply = (new Execution())->runIn($session, $query);

        self::assertSame($query, $operation->statement);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$precision === 0 ? '0' : '0.' . str_repeat('3', $precision)]], $reply->rows);
        self::assertSame($precision, $reply->columns[0]->decimals);
        self::assertSame(4, $session->variables->read('div_precision_increment'));
    }
}
