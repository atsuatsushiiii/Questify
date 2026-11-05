<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

/**
 * The validator is the shared gate for every form and JSON mutation, so its
 * edge cases are asserted directly instead of only through controllers.
 */
final class ValidatorTest extends TestCase
{
    public function testRequiredFieldFailsWithALabelledMessage(): void
    {
        $validator = Validator::make(['name' => '  '], ['name' => 'required'], ['name' => 'Quest title']);

        $this->assertFalse($validator->validate());
        $this->assertSame(['name' => 'Quest title is required.'], $validator->errors());
        $this->assertSame('Quest title is required.', $validator->firstError());
    }

    public function testOptionalFieldBecomesNullInsteadOfMissing(): void
    {
        $validator = Validator::make([], ['note' => 'string|max:20']);

        $this->assertTrue($validator->validate());
        $this->assertSame(['note' => null], $validator->validated());
    }

    public function testValidatedPayloadDropsUndeclaredFields(): void
    {
        $validator = Validator::make(
            ['title' => 'Read', 'role' => 'admin'],
            ['title' => 'required|string|max:120'],
        );

        $this->assertTrue($validator->validate());
        $this->assertSame(['title' => 'Read'], $validator->validated());
    }

    public function testArrayInputIsRejectedRatherThanCast(): void
    {
        $validator = Validator::make(['tags' => ['a', 'b']], ['tags' => 'required']);

        $this->assertFalse($validator->validate());
        $this->assertSame('Tags is not valid.', $validator->firstError());
    }

    public function testEmailRuleRejectsMalformedAddresses(): void
    {
        $this->assertTrue(Validator::make(['email' => 'a@b.co'], ['email' => 'email'])->validate());

        $invalid = Validator::make(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertFalse($invalid->validate());
        $this->assertSame('Email must be a valid email address.', $invalid->firstError());
    }

    public function testUsernameRuleEnforcesLengthAndCharacterSet(): void
    {
        $this->assertTrue(Validator::make(['username' => 'quest_master'], ['username' => 'username'])->validate());

        foreach (['ab', 'has space', 'dash-name', str_repeat('a', 31)] as $candidate) {
            $validator = Validator::make(['username' => $candidate], ['username' => 'username']);
            $this->assertFalse($validator->validate(), "Expected {$candidate} to fail");
        }
    }

    public function testPasswordRuleRequiresALetterAndANumber(): void
    {
        $this->assertTrue(Validator::make(['password' => 'Quest123'], ['password' => 'password'])->validate());
        $this->assertFalse(Validator::make(['password' => 'onlyletters'], ['password' => 'password'])->validate());
        $this->assertFalse(Validator::make(['password' => '12345678'], ['password' => 'password'])->validate());
    }

    public function testConfirmedRuleComparesAgainstTheConfirmationField(): void
    {
        $matching = Validator::make(
            ['password' => 'Quest123', 'password_confirmation' => 'Quest123'],
            ['password' => 'confirmed'],
        );
        $this->assertTrue($matching->validate());

        $mismatch = Validator::make(
            ['password' => 'Quest123', 'password_confirmation' => 'Quest124'],
            ['password' => 'confirmed'],
        );
        $this->assertFalse($mismatch->validate());
        $this->assertSame('Password does not match.', $mismatch->firstError());
    }

    public function testInRuleOnlyAcceptsTheDeclaredList(): void
    {
        $this->assertTrue(
            Validator::make(['difficulty' => 'epic'], ['difficulty' => 'in:easy,medium,hard,epic'])->validate(),
        );

        $validator = Validator::make(['difficulty' => 'impossible'], ['difficulty' => 'in:easy,medium,hard,epic']);
        $this->assertFalse($validator->validate());
    }

    public function testTimezoneRuleUsesTheRuntimeIdentifierList(): void
    {
        $this->assertTrue(
            Validator::make(['timezone' => 'Europe/London'], ['timezone' => 'timezone'])->validate(),
        );
        $this->assertFalse(
            Validator::make(['timezone' => 'Mars/Olympus'], ['timezone' => 'timezone'])->validate(),
        );
    }

    public function testHexRuleChecksLengthWhenGiven(): void
    {
        $this->assertTrue(Validator::make(['color' => 'FACC15'], ['color' => 'hex:6'])->validate());
        $this->assertFalse(Validator::make(['color' => 'FACC1'], ['color' => 'hex:6'])->validate());
        $this->assertFalse(Validator::make(['color' => 'GGGGGG'], ['color' => 'hex:6'])->validate());
    }

    public function testMinAndMaxMeasureMultibyteLength(): void
    {
        $this->assertTrue(Validator::make(['name' => 'Maraño'], ['name' => 'min:3|max:6'])->validate());
        $this->assertFalse(Validator::make(['name' => 'Maraño!'], ['name' => 'max:6'])->validate());
    }

    public function testOnlyTheFirstErrorPerFieldIsReported(): void
    {
        $validator = Validator::make(['email' => 'nope'], ['email' => 'min:50|email']);

        $this->assertFalse($validator->validate());
        $this->assertCount(1, $validator->errors());
        $this->assertSame('Email must be at least 50 characters.', $validator->firstError());
    }

    public function testAcceptedRuleUnderstandsCheckboxValues(): void
    {
        foreach (['1', 'on', 'yes', 'true'] as $value) {
            $this->assertTrue(
                Validator::make(['terms' => $value], ['terms' => 'accepted'])->validate(),
                "Expected {$value} to be accepted",
            );
        }

        $this->assertFalse(Validator::make(['terms' => 'no'], ['terms' => 'accepted'])->validate());
    }
}
