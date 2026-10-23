<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Fake\MemoryClient;
use Tests\Generator\Choices;

/**
 * A generated grouped declaration retains the same operand across catalog changes.
 */
#[CoversNothing]
#[Medium]
final class GeneratedGroupsProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.7.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 10; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Constructs every operation directly and retries the unchanged procedure call.
     */
    #[DataProvider('providerHistories')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesAConditionalGenerationDefinitionRetainsItsOperandAcrossCatalogStates(string $release, int $seed): void
    {
        $choices = new Choices('generated-groups:' . $seed);
        $kinds = [M\Call\Aggregate\AggregateFunction::Sum, M\Call\Aggregate\AggregateFunction::Maximum, M\Call\Aggregate\AggregateFunction::Average];
        $expression = new M\Call\Aggregate\Aggregate($kinds[$choices->integer(0, 2)], [new M\Name\ColumnUse(new Name('a'))]);
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $generated = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), $expression));
        $body = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')), [$column, $generated], ifNotExists: true);
        $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), $body);
        $call = new M\Dml\ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $session->text = 'opaque request label';
        $memory = new MemoryClient();
        self::assertArrayNotHasKey('error', $memory->observe($session, $create));
        $before = $memory->observe($session, $call);
        self::assertSame([1111, 'HY000', 'Invalid use of group function'], $before['error'] ?? null);
        self::assertArrayNotHasKey('error', $memory->observe($session, new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column])));
        $insert = new M\Dml\Insert\InsertRows(new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget(new QualifiedName(new Name('t')))), [new M\Dml\Insert\InsertedRow([new M\Literal\NumberLiteral((string) $choices->integer(1, 1000))])]);
        self::assertArrayNotHasKey('error', $memory->observe($session, $insert));
        $present = $memory->observe($session, $call);
        self::assertArrayNotHasKey('error', $present);
        self::assertSame([['Note', '1050', "Table 't' already exists"]], $present['warnings']);
        self::assertArrayNotHasKey('error', $memory->observe($session, new M\Alter\DropTable(false, false, [new M\Alter\TargetTable(new QualifiedName(new Name('t')))])));
        self::assertSame($before['error'], $memory->observe($session, $call)['error'] ?? null);
        self::assertSame($body, $create->body);
        self::assertInstanceOf(M\Table\Column\Generation\DeferredGeneratedColumn::class, $generated->specification);
        self::assertSame($expression, $generated->specification->expression);
        self::assertSame('opaque request label', $session->text);
    }
}
