<?php

namespace App\Services;

use App\Models\Customer;

class SalesDocumentService
{
    public function __construct(private CompanySettingsService $companySettingsService) {}

    public function company(): array
    {
        return $this->companySettingsService->getForDocument();
    }

    public function buyer(Customer $customer): array
    {
        $primaryContact = $this->primaryRecord($customer->contacts ?? []);
        $address = $this->primaryRecord($customer->addresses ?? []);
        $contactName = trim(($primaryContact['first_name'] ?? '').' '.($primaryContact['last_name'] ?? ''));

        return [
            'id' => $customer->id,
            'name' => $customer->display_name,
            'contact_name' => $customer->type === 'company'
                ? ($contactName !== '' ? $contactName : $customer->contact_name)
                : null,
            'contact_title' => $primaryContact['title'] ?? null,
            'email' => $this->firstFilled([
                $primaryContact['email'] ?? null,
                $customer->email,
                $customer->contact_email,
            ]),
            'phone' => $this->firstFilled([
                $primaryContact['phone'] ?? null,
                $customer->phone,
                $customer->contact_phone,
            ]),
            'website' => $customer->website,
            'tax_id' => $customer->tax_id,
            'address' => [
                'line_1' => $address['line_1'] ?? null,
                'line_2' => $address['line_2'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'country' => $address['country'] ?? null,
            ],
        ];
    }

    public function forCustomer(Customer $customer): array
    {
        return [
            'company' => $this->company(),
            'buyer' => $this->buyer($customer),
        ];
    }

    private function primaryRecord(array $records): array
    {
        foreach ($records as $record) {
            if (($record['is_primary'] ?? false) === true) {
                return $record;
            }
        }

        return $records[0] ?? [];
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
