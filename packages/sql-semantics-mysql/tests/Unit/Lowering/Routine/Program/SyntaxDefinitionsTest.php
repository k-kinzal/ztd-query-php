<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\SyntaxDefinitions;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;

#[CoversClass(SyntaxDefinitions::class)]
#[Medium]
final class SyntaxDefinitionsTest extends TestCase
{
    public function testLoadStopsAtTheLegacyFormatBeforeAnyInputOperands(): void
    {
        $tree = (new MySqlParser('mysql-5.7.44'))->parse("LOAD XML INFILE 'f' INTO TABLE t");
        $node = $tree->find('data_or_xml')[0];
        $failure = (new SyntaxDefinitions())->load($node, true);
        self::assertSame('LOAD XML is not allowed in stored procedures', $failure?->problem->message());
        self::assertSame(strlen('LOAD XML'), $failure->end);
        self::assertTrue($failure->aborts);
        self::assertNull((new SyntaxDefinitions())->load($node, false));
    }

    public function testAlterRecognizesTheViewBranchBeforeItsRequiredKeyword(): void
    {
        $tree = (new MySqlParser('mysql-5.7.44'))->parse('ALTER VIEW v AS SELECT 1');
        $failure = (new SyntaxDefinitions())->alter($tree->find('view_suid')[0], $tree->tokens()[1], $tree->tokens());
        self::assertSame('ALTER VIEW is not allowed in stored procedures', $failure?->problem->message());
        self::assertSame(strlen('ALTER '), $failure->end);
        self::assertTrue($failure->aborts);
    }

    public function testHandlerRecognizesTheOperationBoundaryWithoutItsFollowingOperands(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('HANDLER t READ NEXT');
        $node = $tree->find('handler_stmt')[0];
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $node->children[1]);
        self::assertInstanceOf(\SqlParser\Lexer\Token::class, $node->children[2]);
        $failure = (new SyntaxDefinitions())->handler($node->children[1], $node->children[2], $tree->tokens());

        self::assertSame('HANDLER is not allowed in stored procedures', $failure?->problem->message());
        self::assertSame(strlen('HANDLER t READ'), $failure->end);
        self::assertTrue($failure->aborts);
        self::assertNull((new SyntaxDefinitions())->handler($node->children[1], $node->children[2], $tree->tokens(), false));
        self::assertSame('HANDLER is not allowed in stored procedures', (new SyntaxDefinitions())->handler($node, $node->children[2], $tree->tokens(), false)?->problem->message());
    }

    public function testRejectedPreservesAHandlerRestrictionBeforeItsInvalidOperands(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('CREATE PROCEDURE d.p() HANDLER t READ NEXT WHERE ABS()');
        $failure = (new SyntaxWarnings())->rejected($tree, new LanguageProfile(GrammarRelease::MySql847));

        self::assertInstanceOf(RefusedProgramBody::class, $failure?->problem);
        self::assertSame('HANDLER is not allowed in stored procedures', $failure->problem->problem->message());
    }

    public function testRejectedPreservesTheOuterHeaderBeforeANestedFunctionOperand(): void
    {
        $sql = "CREATE PROCEDURE d.p() CREATE DEFINER='u'@'localhost' FUNCTION f() RETURNS INT RETURN CAST(1 AS SIGNED ARRAY)";
        $tree = (new MySqlParser('mysql-8.4.7'))->parse($sql);
        $failure = (new SyntaxWarnings())->rejected($tree, new LanguageProfile(GrammarRelease::MySql847));

        self::assertInstanceOf(RefusedProgramBody::class, $failure?->problem);
        self::assertInstanceOf(ProgramProblem::class, $failure->problem->problem);
        self::assertSame(ProgramRule::RecursiveCreate, $failure->problem->problem->rule);
        self::assertSame('p', $failure->problem->name->name->value);
        self::assertNull($failure->problem->definer);
        self::assertLessThan(strpos($sql, 'CAST'), $failure->end);
    }

    public function testRejectedLeavesAnEventChangeWithoutABodyAlone(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse("ALTER EVENT e COMMENT 'x'");

        self::assertNull((new SyntaxDefinitions())->rejected($tree->find('alter_event_stmt')[0]));
    }
}
