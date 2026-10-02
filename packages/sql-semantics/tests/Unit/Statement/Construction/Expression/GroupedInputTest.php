<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction\Expression;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(C\Expression\GroupedInput::class)]
#[Small]
final class GroupedInputTest extends TestCase
{
    public function testNewInputPreservesItsEvaluationWhenComposedIntoAQuery(): void
    {
        $input = new C\Expression\BinaryInput(new C\Expression\GroupedInput(new C\Expression\BinaryInput(new E\SqliteInteger(new UnsignedInteger('1')), E\SqliteBinaryOperator::Add, new E\SqliteInteger(new UnsignedInteger('2')))), E\SqliteBinaryOperator::Multiply, new E\SqliteInteger(new UnsignedInteger('3')));
        $context = new Catalog(new SearchPath(new Name('main')));
        $query = new Select($context, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition($input, new Name('result')))));
        $result = (new PDO('sqlite::memory:'))->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        $metadata = $result->getColumnMeta(0);
        self::assertIsArray($metadata);
        self::assertSame('result', $metadata['name']);
        self::assertSame(9, $result->fetchColumn());
    }
}
