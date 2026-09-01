<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Memberships\Models\MembershipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMembershipTypePriceRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MembershipTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/MembershipTypes/Index', [
            'membershipTypes' => MembershipType::query()
                ->orderBy('id')
                ->get()
                ->map(fn (MembershipType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'mode' => $type->mode->label(),
                    'sessions_per_week' => $type->sessions_per_week,
                    'entry_count' => $type->entry_count,
                    'validity' => $this->validityLabel($type),
                    'price' => $type->price,
                ]),
        ]);
    }

    public function updatePrice(UpdateMembershipTypePriceRequest $request, MembershipType $membershipType): RedirectResponse
    {
        // Zmieniamy wyłącznie cennik dla NOWYCH karnetów — istniejące memberships nie
        // trzymają ceny (kwota jest zamrożona w payments.amount), więc rozliczenia w toku
        // nie są ruszane.
        $membershipType->update(['price' => $request->price()]);

        return back()->with('success', "Cena „{$membershipType->name}” została zaktualizowana.");
    }

    private function validityLabel(MembershipType $type): string
    {
        if ($type->validity_period_type === null || $type->validity_period_value === null) {
            return '—';
        }

        return match ($type->validity_period_type->value) {
            'miesiac_kalendarzowy' => $type->validity_period_value === 1
                ? 'miesiąc kalendarzowy'
                : "{$type->validity_period_value} mies. kalendarzowe",
            'tygodnie_od_pierwszego_wejscia' => "{$type->validity_period_value} tyg. od 1. wejścia",
            default => '—',
        };
    }
}
