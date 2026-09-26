<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(ObjectTerm::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\ArrayTerm::class)]
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

    public function testSignatureDistinguishesAllocationsAndSnapshotsWithoutLosingOtherIdentity(): void
    {
        $a = new ObjectTerm('Builder', 'case', 'statement', 'allocation');
        $b = new ObjectTerm('Builder', 'case', 'statement', 'other');
        $c = new ObjectTerm('Builder', 'case', 'statement', 'allocation', new \SqlCatalog\Core\Evaluation\ArrayTerm([]));
        self::assertNotSame($a->signature(), $b->signature());
        self::assertNotSame($a->signature(), $c->signature());
        self::assertNotSame($a->signature(), (new ObjectTerm('Builder', 'other', 'statement', 'allocation'))->signature());
        self::assertNotSame($a->signature(), (new ObjectTerm('Builder', 'case', 'other', 'allocation'))->signature());
        self::assertNotSame($a->signature(), (new ObjectTerm('Other', 'case', 'statement', 'allocation'))->signature());
    }
}
