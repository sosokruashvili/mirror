<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MachineRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('machines', 'name')->ignore($this->route('id') ?? $this->input('id')),
            ],
            'position' => 'required|integer|min:0|max:100000',
            'requires_comment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function attributes()
    {
        return [
            'name' => __('machine.name'),
            'position' => __('machine.position'),
        ];
    }
}
