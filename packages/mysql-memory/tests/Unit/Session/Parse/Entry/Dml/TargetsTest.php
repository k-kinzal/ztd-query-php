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

    public function testNamesRegistersQualifiedAndUnqualifiedTargetsInTheSelectedDatabase(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'generated target names';
        $this->expectExceptionCode(1066);
        (new Targets())->names([new QualifiedName(new Name('t')), new QualifiedName(new Name('t'), new Name('d'))], $session);
    }

    public function testStatementUsesPublishedTargetBindingsWithoutSqlText(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $write = new \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete(null, [], new \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput([new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t')), new Name('y'))], [new QualifiedName(new Name('x'))]), \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm::BeforeFrom);
        $statement = \SqlSemantics\Facade\Semantics::fromStatement($session->semantics()->context([], true, new \SqlSemantics\Contract\SearchPath('d'), $session->resolution()), $write);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete::class, $statement);
        $session->text = 'unrelated invalid input';
        $this->expectExceptionCode(1109);
        (new Targets())->statement($statement, $statement->statementInformation(), $session);
    }
    /**
     * @return iterable<string, array{string, string|null, int}>
     */
    public static function providerCharacterPriority(): iterable
    {
        yield '5.6 no database' => ['5.6.51', null, 1046];
        yield '5.7 no database' => ['5.7.44', null, 1115];
        yield '8.0 no database' => ['8.0.44', null, 1115];
        yield '8.4 no database' => ['8.4.7', null, 1115];
        yield '9.1 no database' => ['9.1.0', null, 1115];
        yield '5.6 selected database' => ['5.6.51', 'd', 1115];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerCharacterPriority')]
    public function testStatementPreservesCharsetPriorityWithoutSqlText(string $release, ?string $database, int $number): void
    {
        $session = (new Instance($release, [], ['d']))->connect(database: $database);
        $predicate = new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new Name('missing_charset')));
        $table = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t')), new Name('y'));
        $write = new \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete(null, [], new \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput([$table], [new QualifiedName(new Name('x'))]), \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm::BeforeFrom, $predicate);
        $statement = \SqlSemantics\Facade\Semantics::fromStatement($session->semantics()->context([], true, null, $session->resolution()), $write);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete::class, $statement);
        $session->text = 'opaque generated namespace';
        $this->expectExceptionCode($number);
        (new Targets())->statement($statement, $statement->statementInformation(), $session);
    }

}
