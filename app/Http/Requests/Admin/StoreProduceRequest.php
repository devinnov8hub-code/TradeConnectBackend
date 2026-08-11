<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreProduceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('produce', 'name')->where('category_id', $this->input('category_id')),
            ],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Category not found.',
            'name.required' => 'Produce name is required.',
            'name.unique' => 'This produce already exists in the selected category.',
            'image.required' => 'Produce image is required.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'Image must be a JPEG, PNG, or WebP file.',
            'image.max' => 'Image must not be larger than 5MB.',
        ];
    }
}
