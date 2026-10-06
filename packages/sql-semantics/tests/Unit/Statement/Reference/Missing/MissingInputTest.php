<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Missing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(MissingInput::class)]
#[Medium]
final class MissingInputTest extends TestCase
{
    public function testDescribeNamesWhatADependentFactWaitsFor(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a, ?2 FROM t');

        $first = $operation->field('a')->type;
        $second = $operation->field(1)->type;

        self::assertInstanceOf(Dependent::class, $first);
        self::assertInstanceOf(Dependent::class, $second);
        self::assertSame(['the declaration of relation t'], array_map(static fn (MissingInput $input): string => $input->describe(), $first->missing));
        self::assertSame(['the value bound to parameter ?2'], array_map(static fn (MissingInput $input): string => $input->describe(), $second->missing));
    }
}
