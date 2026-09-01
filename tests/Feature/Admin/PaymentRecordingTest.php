<?php

namespace Tests\Feature\Admin;

use App\Domain\Memberships\Models\Membership;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_non_admin_cannot_register_payment(): void
    {
        $membership = Membership::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.memberships.payments.store', $membership), [
                'amount' => 100,
            ])
            ->assertForbidden();
    }

    public function test_admin_registers_a_pending_payment(): void
    {
        $membership = Membership::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.memberships.payments.store', $membership), [
                'amount' => 160,
                'reported_date' => '2026-09-01',
                'mark_settled' => false,
                'transfer_title' => 'Karnet wrzesień',
            ])
            ->assertRedirect(route('admin.clients.show', $membership->client_id));

        $payment = Payment::sole();

        $this->assertSame($membership->id, $payment->membership_id);
        $this->assertSame($membership->client_id, $payment->client_id);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->settled_date);
        $this->assertSame('160.00', $payment->amount);
    }

    public function test_admin_can_register_a_payment_already_settled(): void
    {
        $membership = Membership::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.memberships.payments.store', $membership), [
                'amount' => 160,
                'mark_settled' => true,
            ])
            ->assertRedirect();

        $payment = Payment::sole();

        $this->assertSame(PaymentStatus::Settled, $payment->status);
        $this->assertNotNull($payment->settled_date);
    }

    public function test_admin_marks_pending_payment_as_settled(): void
    {
        $payment = Payment::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.payments.status', $payment), ['status' => PaymentStatus::Settled->value])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Settled, $payment->status);
        $this->assertNotNull($payment->settled_date);
    }

    public function test_admin_reverts_settled_payment_to_pending(): void
    {
        $payment = Payment::factory()->settled()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.payments.status', $payment), ['status' => PaymentStatus::Pending->value])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->settled_date);
    }

    public function test_payment_status_value_is_validated(): void
    {
        $payment = Payment::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.payments.status', $payment), ['status' => 'nieznany'])
            ->assertSessionHasErrors('status');
    }

    public function test_membership_with_settled_payment_cannot_be_deleted(): void
    {
        $payment = Payment::factory()->settled()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.memberships.destroy', $payment->membership_id))
            ->assertRedirect();

        $this->assertDatabaseHas('memberships', ['id' => $payment->membership_id]);
    }
}
