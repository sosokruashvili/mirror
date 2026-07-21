<?php

namespace App\Http\Requests;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        $id = $this->get('id') ?? $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('expense_categories', 'id')->where(function ($query) use ($id) {
                    if ($id) {
                        $query->where('id', '!=', $id);
                    }
                }),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $id = $this->get('id') ?? $this->route('id');
            $parentId = $this->input('parent_id');

            if (! $id || ! $parentId) {
                return;
            }

            $self = ExpenseCategory::find($id);
            $parent = ExpenseCategory::find($parentId);

            if ($self && $parent && $parent->lft >= $self->lft && $parent->rgt <= $self->rgt) {
                $validator->errors()->add('parent_id', 'A category cannot be nested under itself or its descendants.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => 'name',
            'parent_id' => 'parent',
        ];
    }
}
