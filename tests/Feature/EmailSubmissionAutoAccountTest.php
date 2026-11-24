<?php

namespace Tests\Feature;

use App\Domain\Clients\Models\Client;
use App\Mail\ClientInvitationMail;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailSubmissionAutoAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    private function listClient(string $name): Client
    {
        $client = Client::factory()->create(['invitation_used_at' => null, 'invitation_sent_at' => null]);
        $slug = strtolower(str_replace(' ', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
        $client->user->update(['name' => $name, 'email' => $slug.'@test.pl']);
        $client->user->assignRole('client');

        return $client->fresh('user');
    }

    private function submit(string $name, string $email): void
    {
        $this->post('/dane', ['full_name' => $name, 'email' => $email, 'consent' => true])->assertSessionHas('success');
    }

    public function test_known_client_is_not_bound_automatically(): void
    {
        // ochrona przed przejęciem konta: adres z publicznego formularza nie trafia sam na istniejące konto
        $client = $this->listClient('Joanna Przykładowa');

        $this->submit('Joanna Przykładowa', 'joanna.p@przyklad.pl');

        $this->assertStringEndsWith('@test.pl', $client->user->fresh()->email);
        $this->assertNull($client->fresh()->invitation_sent_at);
        Mail::assertNothingSent();
    }

    public function test_new_person_gets_a_new_account_and_an_invitation(): void
    {
        $this->listClient('Anna Nowak');

        $this->submit('Zofia Nowokrótka', 'zofia.n@przyklad.pl');

        $user = User::where('email', 'zofia.n@przyklad.pl')->sole();
        $this->assertSame('Zofia Nowokrótka', $user->name);
        $this->assertTrue($user->hasRole('client'));
        Mail::assertSent(ClientInvitationMail::class, fn ($m) => $m->hasTo('zofia.n@przyklad.pl'));
    }

    public function test_doubtful_cases_are_left_for_a_human(): void
    {
        $this->listClient('Beata Kowlaczyk');      // literówka na liście
        $this->listClient('Iwona Testowska');
        $this->listClient('Stefania Testowska');

        $this->submit('Beata Kowalczyk', 'beata.k@przyklad.pl');   // podobne nazwisko
        $this->submit('Testowska', 'testowska@przyklad.pl');        // samo nazwisko

        $this->assertFalse(User::where('email', 'beata.k@przyklad.pl')->exists());
        $this->assertFalse(User::where('email', 'testowska@przyklad.pl')->exists());
        $this->assertSame(3, Client::count());
        Mail::assertNothingSent();
    }

    public function test_daily_cap_on_new_accounts(): void
    {
        Client::factory()->count(\App\Domain\Clients\Actions\ProcessEmailSubmission::MAX_NEW_ACCOUNTS_PER_DAY)->create();

        $this->submit('Zofia Nowokrótka', 'zofia.n@przyklad.pl');

        $this->assertFalse(User::where('email', 'zofia.n@przyklad.pl')->exists());
        Mail::assertNothingSent();
    }

    public function test_address_already_on_an_account_changes_nothing(): void
    {
        $client = $this->listClient('Anna Nowak');
        $client->user->update(['email' => 'anna.n@przyklad.pl']);

        $this->submit('Anna Nowak', 'anna.n@przyklad.pl');

        $this->assertSame(1, Client::count());
        Mail::assertNothingSent();
    }
}
