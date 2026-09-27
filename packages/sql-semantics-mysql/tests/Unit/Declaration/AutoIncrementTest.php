<?php

declare(strict_types=1);

namespace Tests\Unit\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversClass(\SqlSemantics\Platform\MySql\Declaration\AutoIncrement::class)]
#[Medium]
final class AutoIncrementTest extends TestCase
{
    #[TestWith(['id INT AUTO_INCREMENT'])]
    #[TestWith(['id INT AUTO_INCREMENT, other INT, KEY(other)'])]
    #[TestWith(['id SERIAL, other SERIAL'])]
    public function testValidateRejectsInvalidAutomaticNumbering(string $columns): void
    {
        $this->expectException(\SqlSemantics\Core\SemanticException::class);
        (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(' . $columns . ')', []);
    }
    #[TestWith(['id INT AUTO_INCREMENT PRIMARY KEY'])]
    #[TestWith(['id INT AUTO_INCREMENT, KEY(id)'])]
    #[TestWith(['id INT SERIAL DEFAULT VALUE'])]
    #[TestWith(['id SERIAL'])]
    public function testValidateAcceptsIndexedNumbering(string $columns): void
    {
        $column = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(' . $columns . ')', [])->resolution?->declarations[0]->columns[0];
        self::assertTrue($column?->autoIncrement);
        self::assertSame(\SqlSemantics\Statement\Declaration\Nullability::NotNull, $column->nullability);
    }

}
