<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateTableLayoutLowering;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateTableLayoutLowering::class)]
#[Medium]
final class CreateTableLayoutLoweringTest extends TestCase
{
    public function testLowerBindsPrimaryFieldsBeforeResolvingTheirDefaults(): void
    {
        $parsed = (new Inspection())->inspect('CREATE TABLE app.a (lo SMALLINT, hi INTEGER, PRIMARY KEY (hi, lo))');
        self::assertInstanceOf(CreateTable::class, $parsed->statement);

        [$fields, $key] = (new CreateTableLayoutLowering())->lower($parsed->statement, GrammarRelease::MySql5651, null);

        self::assertNotNull($key);
        self::assertSame(1, $key->terms[0]->slot);
        self::assertFalse($fields[0]->nullable);
        self::assertFalse($fields[1]->nullable);
        self::assertSame('lo', $fields[0]->name->value);
    }
}
