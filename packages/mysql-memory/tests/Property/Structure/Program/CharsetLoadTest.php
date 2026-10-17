<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Choices;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Directly constructed charset conversions preserve definition and load notices.
 * Native histories independently establish the same cached and invalidated calls.
 */
#[CoversNothing]
#[Medium]
final class CharsetLoadTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int<0, 9>}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['utf8', 'utf8mb3', 'ucs2'] as $charset) {
                for ($seed = 0; $seed < 10; $seed++) {
                    yield $release . ':' . $charset . ':' . $seed => [$release, $charset, $seed];
                }
            }
        }
    }

    /**
     * Reuses the same call structure after loading, caching and invalidating its definition.
     *
     * @param int<0, 9> $seed The typed row generation seed
     */
    #[DataProvider('providerHistories')]
    public function testUnchangedInvocationKeepsTheVersionedCharsetHistory(string $release, string $charset, int $seed): void
    {
        $choices = new Choices('charset-load:' . $seed);
        $values = [];
        $width = $choices->integer(2, 4);
        for ($index = 0; $index < $width; $index++) {
            $values[] = new NumberLiteral((string) $choices->integer(0, 1000));
        }
        $operand = new DeferredCharsetConversion(new Row($values), new CharsetName(new Name($charset)));
        $body = new DeferredSelection([new DeferredProjection($operand)]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([new ConditionalBranch(new UserVariable(new Name('run')), [$body])]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $memory = new Memory();
        $session->text = 'unrelated invalid input';
        $defined = $memory->observe($session, $create);
        self::assertArrayNotHasKey('error', $defined);
        self::assertSame([$charset === 'utf8' ? '3719' : '1287'], array_column($defined['warnings'] ?? [], 1));
        foreach (['SET @run=0', 'SET @run=0', "ALTER PROCEDURE p COMMENT 'reload'", 'SET @run=1'] as $phase => $fixture) {
            $session->query($fixture);
            $session->text = 'unrelated invalid input';
            $outcome = $memory->observe($session, $call);
            self::assertSame($phase === 3 ? 1241 : null, $outcome['error'][0] ?? null);
            self::assertSame($phase === 3 ? ['1241'] : ($charset === 'utf8' && $phase !== 1 ? ['3719'] : []), array_column($outcome['warnings'] ?? [], 1));
        }
        self::assertSame('unrelated invalid input', $session->text);
    }
}
