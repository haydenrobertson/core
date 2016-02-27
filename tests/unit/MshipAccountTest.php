<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

class MshipAccountTest extends TestCase
{
    use DatabaseTransactions;

    private $account;

    public function setUp()
    {
        parent::setUp();

        $this->account = factory(App\Models\Mship\Account::class)->create([
            "name_first" => "John",
            "name_last"  => "Doe",
            "email"      => "i_sleep@gmail.com",
        ]);
    }

    /** @test */
    public function it_stores_basic_member_data()
    {
        $this->seeInDatabase("mship_account", [
            "name_first" => "John",
            "name_last"  => "Doe",
            "email"      => "i_sleep@gmail.com",
        ]);

        $this->assertTrue($this->account->exists);
    }

    /** @test */
    public function it_correctly_formats_names()
    {
        $member = factory(\App\Models\Mship\Account::class)->create([
            "name_first" => "mary",
            "name_last"  => "JANE",
        ]);

        $this->assertEquals("Mary", $member->name_first);
        $this->assertEquals("Jane", $member->name_last);

        $this->seeInDatabase("mship_account", [
            "id"         => $member->id,
            "name_first" => "Mary",
            "name_last"  => "Jane",
        ]);
    }

    /** @test */
    public function it_correctly_determines_if_the_member_name_is_a_valid_display_name()
    {
        $this->assertTrue($this->account->isValidDisplayName($this->account->real_name));
    }

    /** @test */
    public function it_correctly_determines_if_the_nickname_is_a_valid_display_name()
    {
        $this->account->nickname = "Delboy";
        $this->account->save();

        $this->seeInDatabase("mship_account", [
            "id"       => $this->account->id,
            "nickname" => "Delboy",
        ]);

        $fullNickname = "Delboy " . $this->account->name_last;
        $this->assertTrue($this->account->isValidDisplayName($fullNickname));
    }

    /** @test * */
    public function it_correctly_determines_that_a_name_is_still_valid_even_with_a_nickanem_set()
    {
        $this->account->nickname = "Delboy";
        $this->account->save();

        $this->seeInDatabase("mship_account", [
            "id"       => $this->account->id,
            "nickname" => "Delboy",
        ]);

        $this->assertTrue($this->account->isValidDisplayName($this->account->real_name));
    }

    /** @test */
    public function it_correctly_determines_when_there_is_an_invalid_display_name()
    {
        $this->account->nickname = "Delboy";
        $this->account->save();

        $this->seeInDatabase("mship_account", [
            "id"       => $this->account->id,
            "nickname" => "Delboy",
        ]);

        $fullNickname = "Rodney " . $this->account->name_last;
        $this->assertFalse($this->account->isValidDisplayName($fullNickname));
    }

    /** @test */
    public function it_stores_primary_emails_within_the_account_model()
    {
        $this->assertEquals("i_sleep@gmail.com", $this->account->email);

        $this->seeInDatabase("mship_account", [
            "id"    => $this->account->id,
            "email" => "i_sleep@gmail.com",
        ]);
    }

    /** @test */
    public function it_doesnt_permit_storing_of_primary_email_as_secondary()
    {
        $this->setExpectedException(App\Exceptions\Mship\DuplicateEmailException::class);

        $verified = true;
        $email    = $this->account->addSecondaryEmail("i_sleep@gmail.com", $verified);

        $this->assertCount(0, $this->account->fresh()->secondaryEmails);
        $this->assertNotContains($email->id, $this->account->fresh()->secondaryEmails->pluck("id"));
        $this->notSeeInDatabase("mship_account_email", [
            "account_id" => $this->account->id,
            "email"      => "i_sleep@gmail.com",
        ]);
    }

    /** @test */
    public function it_doesnt_permit_duplicate_secondary_emails_on_same_model()
    {
        $this->setExpectedException(App\Exceptions\Mship\DuplicateEmailException::class);

        $verified = true;
        $this->account->addSecondaryEmail("test_email@gmail.com", $verified);
        $this->account->fresh()->addSecondaryEmail("test_email@gmail.com", $verified);
    }

    /** @test */
    public function it_allows_secondary_emails_to_be_stored()
    {
        $this->expectsJobs(\App\Jobs\Mship\Email\TriggerNewEmailVerificationProcess::class);

        $verified = false;
        $email    = $this->account->addSecondaryEmail("i_also_sleep@hotmail.com", $verified);

        $this->assertCount(1, $this->account->fresh()->secondaryEmails);
        $this->assertContains($email->id, $this->account->fresh()->secondaryEmails->pluck("id"));

        $this->seeInDatabase("mship_account_email", [
            "account_id" => $this->account->id,
            "email"      => "i_also_sleep@hotmail.com",
        ]);
    }

    /** @test */
    public function it_doesnt_list_new_secondary_emails_as_verified()
    {
        $this->expectsJobs(\App\Jobs\Mship\Email\TriggerNewEmailVerificationProcess::class);

        $verified = false;
        $email    = $this->account->addSecondaryEmail("i_too_sleep@hotmail.com", $verified);

        $this->assertCount(0, $this->account->verified_secondary_emails);
        $this->assertNotContains($email->id, $this->account->verified_secondary_emails->pluck("id"));
    }

    /** @test */
    public function it_lists_secondary_emails_as_verified()
    {
        $this->doesntExpectJobs(\App\Jobs\Mship\Email\TriggerNewEmailVerificationProcess::class);

        $verified = true;
        $email    = $this->account->addSecondaryEmail("i_three_sleep@hotmail.com", $verified);

        $this->assertContains($email->id, $this->account->fresh()->verified_secondary_emails->pluck("id"));
    }

    /** @test */
    public function it_deletes_email_from_db()
    {
        $verified = true;
        $email    = $this->account->addSecondaryEmail("i_four_sleep@gmail.com", $verified);

        $this->assertContains($email->id, $this->account->fresh()->secondaryEmails->pluck("id"));

        $email->delete();

        $this->assertEquals(false, $email->exists);
        $this->assertNotContains($email->id, $this->account->fresh()->secondaryEmails->pluck("id"));
    }

    /** @test */
    public function it_touches_account_updated_at_when_adding_an_email()
    {
        $originalUpdatedAt = $this->account->updated_at;

        sleep(1);

        $verified = true;
        $email    = $this->account->addSecondaryEmail("i_four_sleep@gmail.com", $verified);
        $email->save();

        $this->assertNotEquals($originalUpdatedAt, $this->account->fresh()->updated_at);
    }

    /** @test */
    public function it_correctly_stores_qualifications()
    {
        $qualification = factory(\App\Models\Mship\Qualification::class)->create();

        $this->account->addQualification($qualification);

        $this->assertTrue($this->account->fresh()->hasQualification($qualification));

        $this->seeInDatabase("mship_account_qualification", [
            "account_id"       => $this->account->id,
            "qualification_id" => $qualification->qualification_id,
            "deleted_at"       => null,
        ]);
    }

    /** @test */
    public function it_returns_duplicate_qualification_error_when_adding_qualification()
    {
        $this->setExpectedException(\App\Exceptions\Mship\DuplicateQualificationException::class);

        $qualification = factory(\App\Models\Mship\Qualification::class)->create();

        $this->account->addQualification($qualification);
        $this->account->fresh()->addQualification($qualification);
    }

    /** @test */
    public function it_returns_the_correct_account_based_on_slack_id()
    {
        $slackID = substr(strrev(uniqid()), 0, 10);

        $this->account->slack_id = $slackID;
        $this->account->save();


        $slackAccount = \App\Models\Mship\Account::findWithSlackId($slackID);

        $this->assertEquals($slackAccount->id, $this->account->fresh()->id);
    }

    /** @test * */
    public function it_correctly_stores_state()
    {
        $this->account->setState(\App\Models\Mship\Account\State::STATE_DIVISION);
        $this->account = $this->account->fresh();

        $this->assertTrue($this->account->hasState(\App\Models\Mship\Account\State::STATE_DIVISION));
        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state"      => \App\Models\Mship\Account\State::STATE_DIVISION,
            "deleted_at" => null,
        ]);
    }

    /** @test * */
    public function it_correctly_throws_duplicate_error_when_trying_to_set_the_same_state()
    {
        $this->setExpectedException(\App\Exceptions\Mship\DuplicateStateException::class);

        $this->account->setState(\App\Models\Mship\Account\State::STATE_DIVISION);
        $this->account->fresh()->setState(\App\Models\Mship\Account\State::STATE_DIVISION);
    }

    /** @test * */
    public function it_correctly_deletes_old_states_when_adding_a_new_state()
    {
        $this->account->setState(\App\Models\Mship\Account\State::STATE_DIVISION);
        $this->account = $this->account->fresh();

        $this->assertTrue($this->account->hasState(\App\Models\Mship\Account\State::STATE_DIVISION));
        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state"      => \App\Models\Mship\Account\State::STATE_DIVISION,
            "deleted_at" => null,
        ]);

        $this->account->setState(\App\Models\Mship\Account\State::STATE_REGION);
        $this->account = $this->account->fresh();

        $this->assertFalse($this->account->hasState(\App\Models\Mship\Account\State::STATE_DIVISION));
        $this->notSeeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state"      => \App\Models\Mship\Account\State::STATE_DIVISION,
            "deleted_at" => null,
        ]);

        $this->assertTrue($this->account->hasState(\App\Models\Mship\Account\State::STATE_REGION));
        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state"      => \App\Models\Mship\Account\State::STATE_REGION,
            "deleted_at" => null,
        ]);
    }

    /** @test * */
    public function it_correctly_determines_that_password_is_not_set()
    {
        $this->assertFalse($this->account->hasPassword());

        $this->seeInDatabase("mship_account", [
            "id"                  => $this->account->id,
            "password"            => null,
            "password_set_at"     => null,
            "password_expires_at" => null,
        ]);
    }

    /** @test * */
    public function it_correctly_stores_a_hashed_password()
    {
        $this->account->setPassword("testing123");

        $this->account = $this->account->fresh();

        $this->assertTrue(\Hash::check("testing123", $this->account->password));

        $this->seeInDatabase("mship_account", [
            "id"       => $this->account->id,
            "password" => $this->account->password,
        ]);

        $this->notSeeInDatabase("mship_account", [
            "id"                  => $this->account->id,
            "password_set_at"     => null,
            "password_expires_at" => null,
        ]);
    }

    /** @test * */
    public function it_correctly_determines_that_password_is_set()
    {
        $this->account->setPassword("testing456");

        $this->account = $this->account->fresh();

        $this->assertTrue($this->account->hasPassword());

        $this->seeInDatabase("mship_account", [
            "id"       => $this->account->id,
            "password" => $this->account->password,
        ]);

        $this->notSeeInDatabase("mship_account", [
            "id"                  => $this->account->id,
            "password_set_at"     => null,
            "password_expires_at" => null,
        ]);
    }

    /** @test * */
    public function it_correctly_determines_that_password_has_expired()
    {
        $temporary = true;
        $this->account->setPassword("testing911", $temporary);

        sleep(1); // Necessary to check the password has expired.

        $this->account = $this->account->fresh();

        $this->assertTrue($this->account->hasPasswordExpired());
    }

    /** @test **/
    public function it_throws_an_exception_when_the_same_password_is_set()
    {
        $this->setExpectedException(\App\Exceptions\Mship\DuplicatePasswordException::class);

        $this->account->setPassword("testing123");
        $this->account->setPassword("testing123");
    }

    /** @test * */
    public function it_correctly_overwrites_old_password_and_modifies_the_timestamps()
    {
        $this->account->setPassword("testing123");

        $oldPassword          = $this->account->password;
        $oldPasswordSetAt     = $this->account->password_set_at;
        $oldPasswordExpiresAt = $this->account->password_expires_at;

        $this->account = $this->account->fresh();

        $this->seeInDatabase("mship_account", [
            "id"                  => $this->account->id,
            "password"            => $oldPassword,
            "password_set_at"     => $oldPasswordSetAt,
            "password_expires_at" => $oldPasswordExpiresAt,
        ]);

        $this->account->setPassword("testing456");

        $this->notSeeInDatabase("mship_account", [
            "id"                  => $this->account->id,
            "password"            => $oldPassword,
            "password_set_at"     => $oldPasswordSetAt,
            "password_expires_at" => $oldPasswordExpiresAt,
        ]);
    }

    /** @test * */
    public function it_correctly_determines_that_password_is_not_mandatory()
    {
        $this->assertFalse($this->account->mandatory_password);
    }

    /** @test * */
    public function it_correctly_determines_that_password_is_mandatory()
    {
        $role = factory(\App\Models\Mship\Role::class)->create(["password_mandatory" => true]);

        $this->account->addRole($role);

        $this->account = $this->account->fresh();

        $this->assertTrue($this->account->mandatory_password);
    }
}