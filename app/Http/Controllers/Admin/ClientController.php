<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Clients\Actions\CreateClient;
use App\Domain\Clients\Actions\SetClientStatus;
use App\Domain\Clients\Actions\UpdateClient;
use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $clients = Client::query()
            ->with('user:id,name,email')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('user', function (Builder $query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(
                ClientStatus::tryFrom($status),
                fn (Builder $query, ClientStatus $status) => $query->where('status', $status),
            )
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->user->name,
                'email' => $client->user->email,
                'phone' => $client->phone,
                'status' => $client->status->value,
                'status_label' => $client->status->label(),
                'join_date' => $client->join_date?->toDateString(),
            ]);

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => ClientStatus::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Clients/Create', [
            'statuses' => ClientStatus::options(),
        ]);
    }

    public function store(StoreClientRequest $request, CreateClient $createClient): RedirectResponse
    {
        $createClient->handle($request->toData());

        return redirect()->route('admin.clients.index')
            ->with('success', 'Klient został dodany.');
    }

    public function edit(Client $client): Response
    {
        $client->load('user:id,name,email');

        return Inertia::render('Admin/Clients/Edit', [
            'client' => [
                'id' => $client->id,
                'name' => $client->user->name,
                'email' => $client->user->email,
                'phone' => $client->phone,
                'birth_date' => $client->birth_date?->toDateString(),
                'status' => $client->status->value,
                'join_date' => $client->join_date?->toDateString(),
                'terms_accepted' => $client->terms_accepted_at !== null,
                'health_declaration' => $client->health_declaration_at !== null,
                'terms_accepted_at' => $client->terms_accepted_at?->toDateTimeString(),
                'health_declaration_at' => $client->health_declaration_at?->toDateTimeString(),
            ],
            'statuses' => ClientStatus::options(),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClient $updateClient): RedirectResponse
    {
        $updateClient->handle($client, $request->toData());

        return redirect()->route('admin.clients.index')
            ->with('success', 'Dane klienta zostały zaktualizowane.');
    }

    public function updateStatus(Client $client, SetClientStatus $setClientStatus): RedirectResponse
    {
        $status = $setClientStatus->toggle($client);

        return back()->with('success', "Status klienta: {$status->label()}.");
    }
}
