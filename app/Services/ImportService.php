<?php

namespace App\Services;

use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportService
{
    public function start(string $entityType, UploadedFile $file, User $user): Import
    {
        $path = $file->store('imports', 'local');

        $import = Import::create([
            'user_id' => $user->id,
            'entity_type' => $entityType,
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        ProcessImportJob::dispatch($import, $path);

        return $import;
    }

    public function show(Import $import): Import
    {
        return $import->load('user');
    }

    public function list(User $user): Collection
    {
        return Import::where('user_id', $user->id)
            ->latest()
            ->limit(50)
            ->get();
    }

    public function template(string $entityType): StreamedResponse
    {
        $headers = match ($entityType) {
            'leads' => 'type,first_name,last_name,email,phone,company_name,industry,website,source,lead_status,estimated_value,description,notes',
            'customers' => 'type,first_name,last_name,email,phone,company_name,industry,website,source,status,contract_value,description,notes',
            'opportunities' => 'customer_id,title,stage,amount,expected_close_date',
            'products' => 'name,sku,description,unit_price,currency,category_slug,is_active',
            'categories' => 'name,slug,parent_slug,description,sort_order',
            default => '',
        };

        return response()->streamDownload(function () use ($headers) {
            echo $headers."\n";
        }, "{$entityType}-template.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
