<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction\Conditional;

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

#[CoversClass(C\Conditional\CaseArmInput::class)]
#[Small]
final class CaseArmInputTest extends TestCase
{
    public function testNewInputPreservesItsEvaluationWhenComposedIntoAQuery(): void
    {
        $input = new C\Conditional\SearchedCaseInput(new C\Conditional\CaseBranchesInput(new E\SqliteInteger(new UnsignedInteger('9')), new C\Conditional\CaseArmInput(new E\SqliteInteger(new UnsignedInteger('0')), new E\SqliteInteger(new UnsignedInteger('3'))), new C\Conditional\CaseArmInput(new E\SqliteInteger(new UnsignedInteger('1')), new E\SqliteInteger(new UnsignedInteger('7')))));
        $context = new Catalog(new SearchPath(new Name('main')));
        $query = new Select($context, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition($input, new Name('result')))));
        $result = (new PDO('sqlite::memory:'))->query($query->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        $metadata = $result->getColumnMeta(0);
        self::assertIsArray($metadata);
        self::assertSame('result', $metadata['name']);
        self::assertSame(7, $result->fetchColumn());
    }
}
