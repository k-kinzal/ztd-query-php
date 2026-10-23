<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Expression;

use Fuzz\Structure\Choices;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Result\Observation;

/**
 * Separates the fixed integer-token domain from session-dependent window counts.
 */
#[CoversNothing]
#[Medium]
final class WindowOperandDomainsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerCounts(): iterable
    {
        $choices = new Choices('window-count-domain-20261017');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($trial = 0; $trial < 20; $trial++) {
                yield $release . ':' . $trial => [$release, $choices->integer(1, 100)];
            }
        }
    }

    /**
     * A populated query keeps its structure while the window count changes between SQL error and success.
     */
    #[DataProvider('providerCounts')]
    public function testTheSameCountReferenceFollowsSessionValues(string $release, int $count): void
    {
        $statement = new QueryRoot(new Select([], [new SelectExpression(new WindowFunction(WindowFunctionKind::Tile, [new UserVariable(new Name('n'))], new WindowSpec()))], new TableReference(new QualifiedName(new Name('t')))));
        $session = (new Instance($release))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); INSERT INTO t VALUES(1),(2),(3)');
        $memory = new Observation();
        foreach (['0', (string) $count, 'NULL', '1'] as $index => $value) {
            $session->query('SET @n=' . $value);
            $session->text = 'unrelated invalid input';
            $observation = $memory->observe($session, $statement);
            self::assertSame($index === 0 || $index === 2 ? 1210 : null, $observation['error'][0] ?? null);
            self::assertSame($index === 0 || $index === 2 ? 0 : 1, count($observation['results']));
        }
        self::assertSame('unrelated invalid input', $session->text);
    }
}
