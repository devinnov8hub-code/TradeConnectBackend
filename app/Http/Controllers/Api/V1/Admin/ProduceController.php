<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProduceRequest;
use App\Http\Requests\Admin\UpdateProduceRequest;
use App\Models\Produce;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProduceController extends Controller
{
    public function index(): JsonResponse
    {
        $produce = Produce::query()
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $produce]);
    }

    public function store(StoreProduceRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $path = $this->storeImage($file);

        try {
            $produce = Produce::create([
                'category_id' => $request->validated('category_id'),
                'name' => $request->validated('name'),
                'image' => null,
                'image_path' => $path,
                'image_mime' => $file->getMimeType(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return response()->json(['data' => $produce], 201);
    }

    public function show(Produce $produce): JsonResponse
    {
        return response()->json(['data' => $produce]);
    }

    public function update(UpdateProduceRequest $request, Produce $produce): JsonResponse
    {
        $data = $request->safe()->only(['category_id', 'name']);
        $newPath = null;
        $oldPath = $produce->image_path;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $newPath = $this->storeImage($file);

            $data['image'] = null;
            $data['image_path'] = $newPath;
            $data['image_mime'] = $file->getMimeType();
        }

        try {
            $produce->update($data);
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }

            throw $exception;
        }

        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json(['data' => $produce->fresh()]);
    }

    public function destroy(Produce $produce): JsonResponse
    {
        $produce->delete();

        return response()->json(['message' => 'Produce deleted.']);
    }

    private function storeImage(UploadedFile $file): string
    {
        $path = $file->store('produce-images', 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store produce image.');
        }

        return $path;
    }
}
