<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Clients\Models\Client;
use App\Domain\Memberships\Actions\AssignMembership;
use App\Domain\Memberships\Actions\ChangeMembership;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeMembershipRequest;
use App\Http\Requests\Admin\StoreMembershipRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function create(Client $client): Response
    {
        $client->load('user:id,name');

        return Inertia::render('Admin/Memberships/Create', [
            'client' => ['id' => $client->id, 'name' => $client->user->name],
            'membershipTypes' => MembershipType::query()
                ->orderBy('id')
                ->get()
                ->map(fn (MembershipType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'mode' => $type->mode->value,
                    'mode_label' => $type->mode->label(),
                    'price' => $type->price,
                    'entry_count' => $type->entry_count,
                    'validity_period_type' => $type->validity_period_type?->value,
                    'validity_period_value' => $type->validity_period_value,
                ]),
        ]);
    }

    public function store(StoreMembershipRequest $request, Client $client, AssignMembership $assignMembership): RedirectResponse
    {
        $assignMembership->handle($client, $request->toData());

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Karnet został przypisany.');
    }

    public function edit(Membership $membership): Response
    {
        $membership->load(['client.user:id,name', 'membershipType:id,name,sessions_per_week']);
        $currentClasses = $membership->classGroups()->count();

        return Inertia::render('Admin/Memberships/Edit', [
            'membership' => [
                'id' => $membership->id,
                'client_id' => $membership->client_id,
                'client_name' => $membership->client->user->name,
                'month_label' => $membership->start_date?->translatedFormat('F Y'),
                'type_id' => $membership->membership_type_id,
                'type_name' => $membership->membershipType->name,
                'price_locked' => $membership->price_locked,
                'admin_note' => $membership->admin_note,
                'class_groups_count' => $currentClasses,
            ],
            'membershipTypes' => MembershipType::query()
                ->orderBy('id')
                ->get()
                ->map(fn (MembershipType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'mode_label' => $type->mode->label(),
                    'price' => $type->price,
                    'sessions_per_week' => $type->sessions_per_week,
                ]),
        ]);
    }

    public function update(ChangeMembershipRequest $request, Membership $membership, ChangeMembership $changeMembership): RedirectResponse
    {
        $type = MembershipType::findOrFail($request->integer('membership_type_id'));

        $changeMembership->handle($membership, $type, $request->price(), $request->note(), $request->user());

        return redirect()->route('admin.clients.show', $membership->client_id)
            ->with('success', 'Karnet został zmieniony.');
    }

    public function destroy(Membership $membership): RedirectResponse
    {
        $membership->loadCount(['payments as settled_payments_count' => fn ($query) => $query->where('status', 'zaksiegowana')]);

        if ($membership->settled_payments_count > 0) {
            return back()->with('error', 'Nie można usunąć karnetu z zaksięgowaną płatnością.');
        }

        $clientId = $membership->client_id;
        $membership->delete();

        return redirect()->route('admin.clients.show', $clientId)
            ->with('success', 'Karnet został usunięty.');
    }
}
