<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\SystemVariableRead;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;

#[CoversClass(SystemVariableRead::class)]
#[Small]
final class SystemVariableReadTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheValue(): void
    {
        $instance = new Instance();
        $definition = $instance->catalog->find('version');
        self::assertInstanceOf(Definition::class, $definition);
        $domain = Domain::of($definition->domain, true);

        self::assertSame($domain, (new SystemVariableRead($definition, Scope::Session, $domain))->domain());
    }

    public function testEvaluateReadsAnOnOffVariableAsAnInteger(): void
    {
        $session = (new Instance())->connect();
        $definition = $session->instance->catalog->find('autocommit');
        self::assertInstanceOf(Definition::class, $definition);
        $read = new SystemVariableRead($definition, Scope::Session, Domain::of($definition->domain, true));
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        $before = $read->evaluate($frame);
        $session->variables->set($definition, 'OFF');

        self::assertSame([1, 0], [$before, $read->evaluate($frame)]);
    }

    public function testEvaluateReadsTheGlobalValueRatherThanTheSessionValue(): void
    {
        $session = (new Instance())->connect();
        $definition = $session->instance->catalog->find('div_precision_increment');
        self::assertInstanceOf(Definition::class, $definition);
        $session->variables->set($definition, 9);
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([4, 9], [(new SystemVariableRead($definition, Scope::Global, Domain::of($definition->domain, true)))->evaluate($frame), (new SystemVariableRead($definition, Scope::Session, Domain::of($definition->domain, true)))->evaluate($frame)]);
    }

    public function testEvaluateReadsADoubleVariableAsADouble(): void
    {
        $session = (new Instance())->connect();
        $definition = $session->instance->catalog->find('long_query_time');
        self::assertInstanceOf(Definition::class, $definition);

        self::assertSame(10.0, (new SystemVariableRead($definition, Scope::Session, Domain::of($definition->domain, true)))->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))));
    }

    public function testEvaluateReadsAStringVariableAsText(): void
    {
        $session = (new Instance())->connect();
        $definition = $session->instance->catalog->find('time_zone');
        self::assertInstanceOf(Definition::class, $definition);

        self::assertSame('SYSTEM', (new SystemVariableRead($definition, Scope::Session, Domain::of($definition->domain, true)))->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))));
    }

    public function testEvaluateReadsNullFromAVariableThatHoldsIt(): void
    {
        $session = (new Instance())->connect();
        $definition = $session->variables->catalog->find('character_set_results');
        self::assertInstanceOf(Definition::class, $definition);
        $session->variables->set($definition, null);

        self::assertNull((new SystemVariableRead($definition, Scope::Session, Domain::of($definition->domain, true)))->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))));
    }

    public function testEvaluateReadsTheTimestampOfTheStatement(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET timestamp = 1.9999999');

        $reply = $session->query('SELECT @@timestamp')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[1.999999]], $reply->rows);
    }

    public function testEvaluateReadsAParameterOfANamedKeyCache(): void
    {
        $session = (new Instance())->connect();
        $definition = $session->instance->catalog->find('key_cache_block_size');
        self::assertInstanceOf(Definition::class, $definition);
        $session->variables->globals->cache('kc', $definition, 2048);
        $domain = Domain::of($definition->domain, true);

        self::assertSame([2048, 0], [(new SystemVariableRead($definition, Scope::Global, $domain, 'kc'))->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))), (new SystemVariableRead($definition, Scope::Global, $domain, 'other'))->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)))]);
    }
}
