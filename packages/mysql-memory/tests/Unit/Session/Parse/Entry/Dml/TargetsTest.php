<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Dml;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Dml\Targets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Targets::class)]
#[Small]
final class TargetsTest extends TestCase
{
    public function testUnmatchedUsesTheRetainedHeaderAndInputWithoutSqlText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $target = new QualifiedName(new Name('x'));
        $table = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t')), new Name('y'));
        $refusal = new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets([$target], [$table]);
        $program = new QualifiedName(new Name('p'), new Name('d'));
        $input = new Targets();

        self::assertSame(1046, $input->unmatched($refusal, $session)->getCode());
        self::assertSame(1109, $input->unmatched(new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets([$target], [$table], program: $program), $session)->getCode());
        self::assertSame(1066, $input->unmatched(new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets([$target], [$table, new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t')), new Name('y'))], program: $program), $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testErrorResolvesPrefixDependenciesWithoutReadingInput(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $t = new QualifiedName(new Name('t'));
        $qualified = new QualifiedName(new Name('t'), new Name('d'));
        $input = new Targets();
        self::assertSame(1046, $input->error(new RepeatedDeleteTarget([$t, $t]), $session)->getCode());
        self::assertSame(1066, $input->error(new RepeatedDeleteTarget([$qualified, $qualified]), $session)->getCode());
        self::assertSame(1046, $input->error(new RepeatedDeleteTarget([$qualified, $t, $qualified]), $session)->getCode());
        self::assertSame(1066, $input->error(new RepeatedDeleteTarget([$t, $t], new QualifiedName(new Name('p'), new Name('d'))), $session)->getCode());
        self::assertSame(1046, $input->error(new RepeatedDeleteTarget([$qualified, $qualified], new QualifiedName(new Name('p'))), $session)->getCode());
        $session->run('CREATE DATABASE d; USE d');
        $session->text = 'unrelated invalid input';
        self::assertSame(1066, $input->error(new RepeatedDeleteTarget([$t, $t]), $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerCharsetPriority(): iterable
    {
        yield '5.6' => ['5.6.51', 1046];
        yield '5.7' => ['5.7.44', 1115];
        yield '8.0' => ['8.0.44', 1115];
        yield '8.4' => ['8.4.7', 1115];
        yield '9.1' => ['9.1.0', 1115];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerCharsetPriority')]
    public function testUnmatchedPreservesCharsetPriorityWithoutInputText(string $release, int $number): void
    {
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $predicate = new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new Name('missing_charset')));
        $table = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t')), new Name('y'));
        $refusal = new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets([new QualifiedName(new Name('x'))], [$table], where: $predicate);

        self::assertSame($number, (new Targets())->unmatched($refusal, $session)->getCode());
        $session->variables->database = 'd';
        self::assertSame(1115, (new Targets())->unmatched($refusal, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testUnmatchedResolvesModernCollationsBeforeTheTargetWithoutSourceText(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'unrelated invalid input';
        $predicate = new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated(new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['a']), new Name('absent'));
        $table = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t')), new Name('y'));
        $refusal = new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets([new QualifiedName(new Name('x'))], [$table], where: $predicate);
        self::assertSame(1273, (new Targets())->unmatched($refusal, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
        $session->run("WITH c AS (SELECT 'a' COLLATE absent AS v) DELETE x FROM t AS y");
        self::assertSame([['Error', 1273, "Unknown collation: 'absent'"]], $session->diagnostics->conditions);
    }


    public function testFreshLeavesTheInputAvailableForAnotherDatabase(): void
    {
        $target = new QualifiedName(new Name('x'));
        $table = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t')), new Name('y'));
        $original = new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets([$target], [$table]);
        $targets = new Targets();
        $copy = $targets->fresh($original);
        self::assertNotSame($table, $copy->tables[0]);
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        self::assertSame(1046, $targets->unmatched($copy, $session)->getCode());
        $session->variables->database = 'd';
        self::assertSame(1109, $targets->unmatched($original, $session)->getCode());
        self::assertFalse($table->hasBinding());
    }

}
