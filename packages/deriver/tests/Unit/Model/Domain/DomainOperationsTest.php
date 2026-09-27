<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Domain;

use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Domain\DomainFact;
use Deriver\Model\Domain\DomainOperations;
use Deriver\Value\Projection;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DomainOperations::class)]
#[UsesClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
#[Small]
final class DomainOperationsTest extends TestCase
{
    public function testRegistrationChecksTheLatticeBeforeAcceptingItsIdentity(): void
    {
        self::assertSame(['example.policy','1'], (new DomainOperations())->registration(new \Tests\Fake\PolicyDomain()));
    }



    public function testApplyJoinContainsBothInputFacts(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $value = (new DomainOperations())->apply($domain, 'join', $domain->bottom()->term(), $domain->top()->term());
        self::assertSame('top', $value->operands['representation']->kind);
    }

    public function testContainsRequiresTheDeclaredInclusionDirection(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $boundary = new DomainOperations();
        self::assertTrue($boundary->contains($domain, $domain->top()->term(), $domain->bottom()->term()));
        self::assertFalse($boundary->contains($domain, $domain->bottom()->term(), $domain->top()->term()));
    }



    public function testRegistrationPropagatesProviderFailures(): void
    {
        $plugin = self::createStub(AbstractDomain::class);
        $plugin->method('bottom')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sensitive plugin detail');
        (new DomainOperations())->registration($plugin);
    }

    public function testRegistrationRejectsViolationsOfTheDeclaredLaws(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $fact = new DomainFact('broken', Term::constant(1));
        $domain->method('id')->willReturn('broken');
        $domain->method('bottom')->willReturn($fact);
        $domain->method('top')->willReturn($fact);
        $domain->method('join')->willReturn($fact);
        $domain->method('widen')->willReturn($fact);
        $domain->method('lessOrEqual')->willReturn(false);
        $this->expectException(\Deriver\Exception\ModelContractException::class);
        $this->expectExceptionMessage('Invalid domain registration:');
        (new DomainOperations())->registration($domain);
    }

    public function testApplyRetainsSecrecyWhenAProjectionReplacesItsRepresentation(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $input = new Term('domain', $domain->id(), ['representation' => Term::fromNative(['x' => 'private'])], secret:true);
        $projected = (new DomainOperations())->apply($domain, 'project', $input, projection:new Projection(['x']));
        self::assertSame('private', $projected->operands['representation']->native());
        self::assertTrue($projected->isSecret());
    }

    public function testApplyRejectsFactsOwnedByAnotherDomain(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $domain->method('project')->willReturn(new DomainFact('different', Term::constant(1)));
        $input = (new DomainFact('example', Term::constant(2)))->term();
        $this->expectException(\Deriver\Exception\ModelContractException::class);
        (new DomainOperations())->apply($domain, 'project', $input);
    }

    public function testApplyRejectsWideningThatDoesNotContainBothInputs(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $domain->method('widen')->willReturn(new DomainFact('example', Term::constant(1)));
        $domain->method('lessOrEqual')->willReturn(false);
        $a = (new DomainFact('example', Term::constant(1)))->term();
        $b = (new DomainFact('example', Term::constant(2, true)))->term();
        $this->expectException(\Deriver\Exception\ModelContractException::class);
        (new DomainOperations())->apply($domain, 'widen', $a, $b);
    }

    public function testApplyRejectsUnknownOperations(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $this->expectException(\Deriver\Exception\ModelContractException::class);
        (new DomainOperations())->apply($domain, 'invalid', $domain->top()->term());
    }

    public function testApplyPropagatesProviderFailures(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $failure = new RuntimeException('domain failure');
        $domain->method('join')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new DomainOperations())->apply($domain, 'join', (new DomainFact('example', Term::constant(1)))->term());
    }

    public function testContainsCannotTreatAPluginExceptionAsConvergence(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $domain->method('lessOrEqual')->willThrowException(new RuntimeException());
        $input = (new DomainFact('example', Term::constant(1)))->term();
        $this->expectException(RuntimeException::class);
        (new DomainOperations())->contains($domain, $input, $input);
    }
}
