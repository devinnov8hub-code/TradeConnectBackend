<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
            ],
            'reset_token' => [
                'required',
                'string',
                'size:64',
            ],
            'password' => [
                'required',
                'string',
                Password::defaults(),
            ],
        ];
    }
}
