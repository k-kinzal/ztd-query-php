<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;

#[CoversClass(ForeignKey::class)]
#[Medium]
final class ForeignKeyTest extends TestCase
{
    public function testDeriveElementDerivesNothing(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE c (p INT, FOREIGN KEY f (p) REFERENCES c (p))');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $key = $statement->elements[1];

        self::assertInstanceOf(ForeignKey::class, $key);
        self::assertSame('f', $key->name?->column->value);
        self::assertSame([], $create->facts->diagnostics);
    }

    public function testRenderWritesTheConstraintTheColumnsAndTheReference(): void
    {
        self::assertSame('CREATE TABLE c (p INT, CONSTRAINT fk FOREIGN KEY (p) REFERENCES p (id) MATCH SIMPLE ON UPDATE SET DEFAULT)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE c (p INT, CONSTRAINT fk FOREIGN KEY (p) REFERENCES p (id) MATCH SIMPLE ON UPDATE SET DEFAULT)')->toString());
    }
}
