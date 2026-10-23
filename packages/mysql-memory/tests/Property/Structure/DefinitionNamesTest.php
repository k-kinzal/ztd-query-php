<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Result\Observation;

/**
 * Keeps definition-name structures unchanged while database and populated-table states change.
 */
#[CoversNothing]
#[Medium]
final class DefinitionNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, AlterTable}>
     */
    public static function providerHistories(): iterable
    {
        $choices = new Choices('definition-names-20261017');
        foreach (['5.6.51', '5.7.44'] as $release) {
            for ($trial = 0; $trial < 40; $trial++) {
                $column = new ColumnName(new Name('c' . $choices->integer(0, 10000)), new QualifiedName(new Name('t'), new Name('x')));
                yield $release . ':' . $trial => [$release, new AlterTable(new QualifiedName(new Name('t')), [new AddColumn(new ColumnDefinition($column, new OrdinaryColumn(new Integral(IntegralKind::Int))))])];
            }
        }
    }

    /**
     * The same name starts in another database, then a missing table, then populated and duplicate states.
     */
    #[DataProvider('providerHistories')]
    public function testTheSameQualifiedDefinitionFollowsTheCurrentDatabase(string $release, AlterTable $statement): void
    {
        $session = (new Instance($release))->connect();
        $memory = new Observation();
        $session->query('CREATE DATABASE d; CREATE DATABASE x; USE d');
        $session->text = 'unrelated invalid input';
        $other = $memory->observe($session, $statement);
        $session->query('USE x');
        $session->text = 'unrelated invalid input';
        $missing = $memory->observe($session, $statement);
        $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES(1),(2)');
        $session->text = 'unrelated invalid input';
        $success = $memory->observe($session, $statement);
        $duplicate = $memory->observe($session, $statement);
        self::assertSame(1102, $other['error'][0] ?? null);
        self::assertSame(1146, $missing['error'][0] ?? null);
        self::assertArrayNotHasKey('error', $success);
        self::assertSame(1060, $duplicate['error'][0] ?? null);
        self::assertSame('unrelated invalid input', $session->text);
    }

    /**
     * A dot prefix remains observable without any parsed input.
     */
    public function testADirectDotPrefixedNameWarnsBeforeTheMissingTable(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; USE d');
        $statement = new AlterTable(new QualifiedName(new Name('t')), [new AddColumn(new ColumnDefinition(new ColumnName(new Name('a'), dot: OptionalWords::Written), new OrdinaryColumn(new Integral(IntegralKind::Int))))]);
        $session->text = 'unrelated invalid input';
        $outcome = (new Observation())->observe($session, $statement);
        self::assertSame(1146, $outcome['error'][0] ?? null);
        self::assertIsArray($outcome['warnings']);
        self::assertArrayHasKey(0, $outcome['warnings']);
        self::assertSame('1287', $outcome['warnings'][0][1]);
        self::assertSame("'.<column>' is deprecated and will be removed in a future release. Please use the column name without a dot prefix instead", $outcome['warnings'][0][2]);
    }
}
