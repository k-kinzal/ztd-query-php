<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Construction\Occurrences;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Source\Origin;
use SqlSemantics\Statement\Source\SourceMap;

#[CoversClass(Occurrences::class)]
#[Medium]
final class OccurrencesTest extends TestCase
{
    public function testFreshLeavesPublishedBindingsUntouched(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('SELECT 12');
        self::assertInstanceOf(QueryRoot::class, $query);
        self::assertTrue($query->query->hasBinding());
        $copy = Occurrences::fresh($query);
        self::assertNotSame($query, $copy);
        self::assertFalse($copy->query->hasBinding());
        self::assertFalse($copy->hasStatementInformation());
        self::assertTrue($query->query->hasBinding());
    }

    public function testCopyAppliesTypedReplacementsThroughTheirConstructors(): void
    {
        $number = new NumberLiteral('12');
        $copy = (new Occurrences(static fn ($node) => $node === $number ? new NumberLiteral('7') : null))->copy($number);
        self::assertInstanceOf(NumberLiteral::class, $copy);
        self::assertSame('7', $copy->text);
        self::assertSame('12', $number->text);
    }

    public function testNodeRejectsAnInvalidConstructorChange(): void
    {
        $this->expectExceptionMessage('A number literal is an unsigned integer, decimal or floating-point number.');
        (new Occurrences())->node(new NumberLiteral('12'), ['text' => '-1']);
    }

    public function testValuePreservesDeleteTargetsAndRenewsTheirInputOccurrences(): void
    {
        $name = new QualifiedName(new Name('items'));
        $table = new TableReference($name);
        $input = new DeletionInput([$table], [$name], [$table]);
        $copy = (new Occurrences())->value($input);
        self::assertInstanceOf(DeletionInput::class, $copy);
        self::assertSame($input->targets, $copy->targets);
        self::assertNotSame($table, $copy->tables[0]);
        self::assertSame($table, $input->relation($name));
        $this->expectExceptionMessage('Deletion targets must be bound before execution.');
        $copy->relation($name);
    }

    public function testOperandReconstructsAFunctionImplementationWithItsCharacteristics(): void
    {
        $function = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION answer() RETURNS INT DETERMINISTIC RETURN 42');
        self::assertInstanceOf(CreateFunction::class, $function);
        $implementation = (new Occurrences())->operand($function, 'implementation');
        self::assertInstanceOf(FunctionImplementation::class, $implementation);
        self::assertNotSame($function->body, $implementation->body);
        self::assertCount(count($function->characteristics), $implementation->characteristics);
    }

    public function testSourcesFollowTheNewOccurrences(): void
    {
        $number = new NumberLiteral('12');
        $occurrences = new Occurrences(sources: new SourceMap([new Origin($number, 7, 2)]));
        $copy = $occurrences->copy($number);
        self::assertSame($copy, $occurrences->sources()->origins[0]->node);
        $source = $copy->source();
        self::assertNotNull($source);
        self::assertSame(7, $source->offset);
        self::assertSame(2, $source->length);
    }
}
