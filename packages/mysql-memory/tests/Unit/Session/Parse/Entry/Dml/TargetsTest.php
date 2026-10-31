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

    public function testStatementChecksTheTargetNamesOfAPublishedStatementWithoutSqlText(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $write = new \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete(null, [], new \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput([new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t')))], [new QualifiedName(new Name('t')), new QualifiedName(new Name('t'), new Name('d'))]), \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm::BeforeFrom);
        $statement = \SqlSemantics\Facade\Semantics::fromStatement($session->semantics()->context([], true, new \SqlSemantics\Contract\SearchPath('d'), $session->resolution()), $write);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete::class, $statement);
        $session->text = 'unrelated invalid input';
        $this->expectExceptionCode(1066);
        (new Targets())->statement($statement, $statement->statementInformation(), $session);
    }

}
