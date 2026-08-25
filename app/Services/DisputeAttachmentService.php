<?php

namespace App\Services;

use App\Models\DisputeMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DisputeAttachmentService
{
    public function storeForMessage(
        DisputeMessage $message,
        array $files
    ): array {
        $storedPaths = [];

        try {
            foreach (
                $files
                as $file
            ) {
                if (
                    ! $file
                    instanceof UploadedFile
                ) {
                    continue;
                }

                $path =
                    $file->store(
                        'dispute-attachments/'
                        .$message->dispute_id
                        .'/'
                        .$message->id,
                        'local'
                    );

                if (
                    ! is_string(
                        $path
                    )
                    || $path === ''
                ) {
                    throw new RuntimeException(
                        'Failed to store dispute attachment.'
                    );
                }

                $storedPaths[] =
                    $path;

                $message
                    ->attachments()
                    ->create([
                        'path' =>
                            $path,

                        'original_name' =>
                            $file
                                ->getClientOriginalName(),

                        'mime_type' =>
                            $file
                                ->getMimeType()
                            ?? $file
                                ->getClientMimeType()
                            ?? 'application/octet-stream',

                        'size_bytes' =>
                            (int) $file
                                ->getSize(),
                    ]);
            }

            return $storedPaths;
        } catch (Throwable $exception) {
            $this->deletePaths(
                $storedPaths
            );

            throw $exception;
        }
    }

    public function deletePaths(
        array $paths
    ): void {
        foreach (
            $paths
            as $path
        ) {
            if (
                ! is_string(
                    $path
                )
                || $path === ''
            ) {
                continue;
            }

            Storage::disk(
                'local'
            )->delete(
                $path
            );
        }
    }
}