<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Registration;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class DeclarationsTest extends TestCase
{
    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testSymbolsIncludesLexicalClosuresWithoutInferringEntries(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function entry(){$f=fn()=>1;}');
        self::assertCount(3, $session->declarations()->symbols());
        self::assertSame([], $session->entrypoints());
    }
    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testSignaturePreservesPassingModesAndOmission(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function target(string &$name="x", ...$rest){}');
        $signature = $session->declarations()->signature('TARGET');
        self::assertNotNull($signature);
        self::assertTrue($signature->parameters[0]->byReference);
        self::assertSame('x', $signature->parameters[0]->default?->native());
        self::assertTrue($signature->parameters[1]->variadic);
    }
    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testClassDistinguishesLiteralAndComputedDefaults(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php class User{public $a="users";public $b="u"."sers";}');
        $class = $session->declarations()->class('user');
        self::assertNotNull($class);
        self::assertSame('users', $class->properties['a']->default?->native());
        self::assertSame('opaque', $class->properties['b']->default?->kind);
    }
    public function testInitializerDistinguishesAbsentGraphs(): void
    {
        self::assertNull(\Deriver\Model\Registration\Declarations::initializer(null));
    }
}
