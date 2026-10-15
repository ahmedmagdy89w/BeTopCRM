<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    public function rules(): array
    {
        return [
            'lead_status' => ['required', 'string', Rule::in(Customer::LEAD_STATUSES)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Customer $customer */
            $customer = $this->route('customer');
            if ($customer && ! $customer->is_lead) {
                $validator->errors()->add('lead_status', 'Only lead customers can transition lead status.');

                return;
            }
            if ($customer && ! $customer->canTransitionLeadTo($this->input('lead_status'))) {
                $validator->errors()->add(
                    'lead_status',
                    "Cannot transition from [{$customer->lead_status}] to [{$this->input('lead_status')}]."
                );
            }
        });
    }
}
