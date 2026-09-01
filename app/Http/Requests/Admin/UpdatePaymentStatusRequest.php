<?php

namespace App\Http\Requests\Admin;

use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(PaymentStatus::class)],
        ];
    }

    public function status(): PaymentStatus
    {
        return PaymentStatus::from($this->string('status')->toString());
    }
}
