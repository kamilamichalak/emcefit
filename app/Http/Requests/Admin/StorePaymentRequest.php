<?php

namespace App\Http\Requests\Admin;

use App\Domain\Payments\Data\PaymentData;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'reported_date' => ['nullable', 'date'],
            'mark_settled' => ['boolean'],
            // pusta data przy zaznaczonym "zaksięgowana" => akcja przyjmie dzisiejszą
            'settled_date' => ['nullable', 'date'],
            'transfer_title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): PaymentData
    {
        return new PaymentData(
            amount: (float) $this->input('amount'),
            reportedDate: $this->date('reported_date')?->toDateString(),
            settledDate: $this->date('settled_date')?->toDateString(),
            markSettled: $this->boolean('mark_settled'),
            transferTitle: $this->filled('transfer_title')
                ? $this->string('transfer_title')->trim()->toString()
                : null,
        );
    }
}
