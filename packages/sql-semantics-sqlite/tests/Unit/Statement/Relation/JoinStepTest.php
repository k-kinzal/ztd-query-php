<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(JoinStep::class)]
#[Medium]
final class JoinStepTest extends TestCase
{
    public function testRenderWritesOperatorRelationAndConstraint(): void
    {
        $semantics = new Inspection();
        $step = new JoinStep(new JoinOperator(false, [JoinKeyword::Left]), new TableInput(new QualifiedName(new Name('u')), new Name('x')), new JoinUsing([new Name('a')]));
        $built = new InspectedStatement($semantics->context(), new Select([new Star()], new JoinChain(new TableInput(new QualifiedName(new Name('t'))), [$step])));
        $query = $semantics->inspect('select * from t left join u using (a) join u as u2');

        self::assertSame('SELECT * FROM t LEFT JOIN u AS x USING (a)', $built->toString());
        self::assertSame('SELECT * FROM t LEFT JOIN u USING (a) JOIN u AS u2', $query->toString());
        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(JoinChain::class, $query->statement->from);
        self::assertSame([JoinKeyword::Left], $query->statement->from->steps[0]->operator->words);
        self::assertInstanceOf(TableInput::class, $query->statement->from->steps[0]->relation);
        self::assertInstanceOf(JoinUsing::class, $query->statement->from->steps[0]->constraint);
        self::assertNull($query->statement->from->steps[1]->constraint);
        $second = $query->statement->from->steps[1]->relation;
        self::assertInstanceOf(TableInput::class, $second);
        self::assertSame('u2', $second->alias?->value);
    }

    public function testRejectsAJoinChainAsTheJoinedRelation(): void
    {
        $chain = new JoinChain(new TableInput(new QualifiedName(new Name('t'))), [new JoinStep(new JoinOperator(), new TableInput(new QualifiedName(new Name('u'))))]);

        $this->expectExceptionMessage('A join chain used as a term is written in parentheses.');

        new JoinStep(new JoinOperator(true), $chain);
    }
}
