<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;

#[CoversClass(FactorChange::class)]
#[Medium]
final class FactorChangeTest extends TestCase
{
    /**
     * @return iterable<string, array{\SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction}>
     */
    public static function providerActions(): iterable
    {
        foreach (\SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction::cases() as $action) {
            yield $action->name => [$action];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerActions')]
    public function testRejectsRepeatedFactorsForEveryAction(\SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction $action): void
    {
        $this->expectExceptionMessage('A factor change names each factor at most once.');

        new FactorChange(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('u')), $action, [new \SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep(\SqlSemantics\Platform\MySql\Statement\Account\User\Factor::Second), new \SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep(\SqlSemantics\Platform\MySql\Statement\Account\User\Factor::Second)]);
    }

    public function testRejectsAddingTheThirdFactorBeforeTheSecond(): void
    {
        $this->expectExceptionMessage('The second factor is added before the third.');

        new FactorChange(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('u')), \SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction::Add, [new \SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep(\SqlSemantics\Platform\MySql\Statement\Account\User\Factor::Third), new \SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep(\SqlSemantics\Platform\MySql\Statement\Account\User\Factor::Second)]);
    }

    public function testRenderWritesEveryStep(): void
    {
        self::assertSame('ALTER USER u DROP 2 FACTOR DROP 3 FACTOR', (new Semantics(Dialect::MySql))->analyze('alter user u drop 2 factor drop 3 factor')->toString());
    }
}
