<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\DeleteUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DeleteUse::class)]
#[Medium]
final class DeleteUseTest extends TestCase
{
    /**
     * @return iterable<string, array{M\Routine\Program\Write\DeferredMultipleDelete}>
     */
    public static function providerWrites(): iterable
    {
        $query = new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('source'))));
        yield 'derived-target' => [new M\Routine\Program\Write\DeferredMultipleDelete(null, [], new M\Dml\Target\DeletionInput([new M\Relation\DerivedTable($query, new Name('q'))]), M\Dml\MultipleDeleteForm::BeforeFrom)];
    }

    #[DataProvider('providerWrites')]
    public function testErrorPreparesDependenciesBeforeTheTargetRole(M\Routine\Program\Write\DeferredMultipleDelete $write): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        self::assertSame(1146, (new DeleteUse())->error($write, $session)->getCode());
        $session->query('CREATE TABLE source(a INT)');
        $session->text = 'unrelated invalid input';
        self::assertSame(1288, (new DeleteUse())->error($write, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }

    #[DataProvider('providerWrites')]
    public function testRaiseRequiresTheDestinationNamespace(M\Routine\Program\Write\DeferredMultipleDelete $write): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionMessage('No database selected');
        (new DeleteUse())->raise($write, $session);
    }

}
