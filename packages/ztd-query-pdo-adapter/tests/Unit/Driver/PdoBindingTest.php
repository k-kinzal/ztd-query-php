<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Driver\PdoBinding;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

#[CoversClass(PdoBinding::class)]
#[UsesClass(ZtdPdoException::class)]
final class PdoBindingTest extends TestCase
{
    public function testItRemembersWhatWasBoundAndHowItWasBound(): void
    {
        $binding = new PdoBinding('value', PDO::PARAM_STR, 32, 'options');

        self::assertSame(['value', PDO::PARAM_STR, 32, 'options'], [
            $binding->value,
            $binding->type,
            $binding->maxLength,
            $binding->driverOptions,
        ]);
    }

    public function testABindingMadeWithNothingElseReservesNoLengthAndNoOptions(): void
    {
        $binding = new PdoBinding(1);

        self::assertSame([PDO::PARAM_STR, 0, null], [$binding->type, $binding->maxLength, $binding->driverOptions]);
    }

    public function testABindingFollowsTheVariableItWasBoundTo(): void
    {
        $variable = 'before';
        $binding = new PdoBinding($variable);
        $binding->value = &$variable;
        $variable = 'after';

        self::assertSame('after', $binding->value);
    }

    public function testBindableAnswersAScalarAsItStands(): void
    {
        self::assertSame(7, PdoBinding::bindable(7, 1));
    }

    public function testBindableAnswersNothingWhereThereIsNothingToBind(): void
    {
        self::assertNull(PdoBinding::bindable(null, ':name'));
    }

    public function testBindableAnswersAStreamAsItStands(): void
    {
        $stream = fopen('php://memory', 'rb');

        self::assertSame($stream, PdoBinding::bindable($stream, 1));
    }

    public function testBindableRefusesAValuePdoCannotBindByPosition(): void
    {
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('Parameter #2 cannot be bound to a value of type array.');

        PdoBinding::bindable([1, 2], 2);
    }

    public function testBindableRefusesAValuePdoCannotBindByName(): void
    {
        $this->expectExceptionMessage('Parameter "name" cannot be bound to a value of type stdClass.');

        PdoBinding::bindable((object) [], 'name');
    }
}
