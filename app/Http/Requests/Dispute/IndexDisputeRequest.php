<?php

namespace App\Http\Requests\Dispute;

use App\Enums\DisputeStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexDisputeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Keep accepting the enhanced alias "under_review"
     * without mutating the actual query string.
     *
     * Pagination links therefore retain whatever the client
     * sent, while validated() returns the canonical v1 value
     * "open" used by the database.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $data =
            parent::validationData();

        if (
            ($data['status'] ?? null)
            === 'under_review'
        ) {
            $data['status'] =
                DisputeStatus::UnderReview
                    ->value;
        }

        return $data;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'status' => [
                'sometimes',
                'nullable',

                Rule::enum(
                    DisputeStatus::class
                ),
            ],

            /*
             * unread=1 means show unread threads only.
             * Omit it for all threads.
             */
            'unread' => [
                'sometimes',
                'boolean',
            ],

            'sort' => [
                'sometimes',
                'nullable',
                'string',

                Rule::in([
                    'created_at',
                    'updated_at',
                    'status',
                    'subject',
                ]),
            ],

            'order' => [
                'sometimes',
                'nullable',
                'string',

                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],

            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.enum' =>
                'Status must be open, under_review, resolved, or closed.',

            'unread.boolean' =>
                'Unread must be true or false.',

            'sort.in' =>
                'Sort must be one of: created_at, updated_at, status, subject.',

            'order.in' =>
                'Order must be asc or desc.',

            'page.integer' =>
                'Page must be an integer.',

            'page.min' =>
                'Page must be at least 1.',

            'per_page.integer' =>
                'Per page must be an integer.',

            'per_page.min' =>
                'Per page must be at least 1.',

            'per_page.max' =>
                'Per page cannot exceed 100.',
        ];
    }
}