<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    public function list(Model $entity): Collection
    {
        return $entity->attachments()->with('uploader:id,name')->orderBy('created_at', 'desc')->get();
    }

    public function store(Model $entity, array $files, User $user): array
    {
        $disk     = config('crm.attachments.disk', 'local');
        $entityType = strtolower(class_basename($entity));
        $results  = [];

        foreach ($files as $file) {
            /** @var UploadedFile $file */
            $storedName = $file->hashName();
            $path       = "attachments/{$entityType}/{$entity->id}/{$storedName}";

            Storage::disk($disk)->putFileAs(
                "attachments/{$entityType}/{$entity->id}",
                $file,
                $storedName
            );

            $results[] = $entity->attachments()->create([
                'uploaded_by'   => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_name'   => $storedName,
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
                'disk'          => $disk,
                'path'          => $path,
            ]);
        }

        return $results;
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }
}
