<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Session\CopyArguments;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;

#[CoversClass(CopyArguments::class)]
#[UsesClass(ZtdPdoException::class)]
#[Small]
final class CopyArgumentsTest extends TestCase
{
    public function testStringsKeepTheirNamesAndContents(): void
    {
        self::assertSame(['tableName' => 'users', 'separator' => '', 'nullAs' => 'NULL'], (new CopyArguments())->strings(['tableName' => 'users', 'separator' => '', 'nullAs' => 'NULL']));
    }

    public function testStringsRejectInvalidValuesAndReportTheFirstArgumentInNativeOrder(): void
    {
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY argument $tableName must be a string, int given.');
        (new CopyArguments())->strings(['tableName' => 7, 'separator' => false]);
    }

    public function testFieldsPreserveNullAndExplicitEmptyLists(): void
    {
        $arguments = new CopyArguments();
        self::assertNull($arguments->fields(null));
        self::assertSame('', $arguments->fields(''));
        self::assertSame('id, name', $arguments->fields('id, name'));
    }

    public function testFieldsRejectNonStringValues(): void
    {
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY argument $fields must be a string, array given.');
        (new CopyArguments())->fields(['id']);
    }

    public function testRowsMaterializeTraversableValuesInInputOrder(): void
    {
        self::assertSame(['first', '', 'last'], (new CopyArguments())->rows(new ArrayIterator(['a' => 'first', 4 => '', 'b' => 'last'])));
        self::assertSame([], (new CopyArguments())->rows([]));
    }

    public function testRowsRejectAnInvalidValueAfterValidRows(): void
    {
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PostgreSQL COPY rows must be strings, bool given.');
        (new CopyArguments())->rows(['first', false]);
    }
}
