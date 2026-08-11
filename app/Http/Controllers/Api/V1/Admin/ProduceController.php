<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProduceRequest;
use App\Http\Requests\Admin\UpdateProduceRequest;
use App\Models\Produce;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

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
        $produce = Produce::create([
            'category_id' => $request->validated('category_id'),
            'name' => $request->validated('name'),
            ...$this->encodeImage($request->file('image')),
        ]);

        return response()->json(['data' => $produce], 201);
    }

    public function show(Produce $produce): JsonResponse
    {
        return response()->json(['data' => $produce]);
    }

    public function update(UpdateProduceRequest $request, Produce $produce): JsonResponse
    {
        $data = $request->safe()->only(['category_id', 'name']);

        if ($request->hasFile('image')) {
            $data = [...$data, ...$this->encodeImage($request->file('image'))];
        }

        $produce->update($data);

        return response()->json(['data' => $produce->fresh()]);
    }

    public function destroy(Produce $produce): JsonResponse
    {
        $produce->delete();

        return response()->json(['message' => 'Produce deleted.']);
    }

    /**
     * @return array{image: string, image_mime: string}
     */
    private function encodeImage(UploadedFile $file): array
    {
        return [
            'image' => base64_encode(file_get_contents($file->getRealPath())),
            'image_mime' => $file->getMimeType(),
        ];
    }
}
