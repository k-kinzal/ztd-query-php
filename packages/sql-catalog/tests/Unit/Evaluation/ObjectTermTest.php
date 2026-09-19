<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(ObjectTerm::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class ObjectTermTest extends TestCase
{
    public function testToPatternLeavesAGap(): void
    {
        self::assertSame('{$}', (new ObjectTerm('PDO'))->toPattern()->display());
    }

    public function testTypeIsTheClass(): void
    {
        self::assertSame('PDO', (new ObjectTerm('PDO'))->type()->display());
    }

    public function testSignatureIsWrittenExactly(): void
    {
        self::assertSame('object:App\\Status:Active:', (new ObjectTerm('App\\Status', 'Active'))->signature());
        self::assertSame('object:PDOStatement::a.php:3:pdo.prepare', (new ObjectTerm('PDOStatement', null, 'a.php:3:pdo.prepare'))->signature());
        self::assertSame('object:PDO::', (new ObjectTerm('PDO'))->signature());
    }

    public function testSignatureSeparatesCasesAndStatements(): void
    {
        $case = new ObjectTerm('App\\Status', 'Active');
        $statement = new ObjectTerm('PDOStatement', null, 'a.php:3:pdo.prepare');
        self::assertNotSame($case->signature(), (new ObjectTerm('App\\Status', 'Banned'))->signature());
        self::assertStringContainsString('a.php:3:pdo.prepare', $statement->signature());
    }
}
