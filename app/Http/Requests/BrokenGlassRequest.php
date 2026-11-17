<?php

namespace App\Http\Requests;

use App\Models\BrokenGlass;
use App\Support\BrokenGlass\BreakDetails;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit form on the Broken Glasses page (production lead correcting a pending
 * record before confirming it). Same rules and messages as the team dialog.
 */
class BrokenGlassRequest extends FormRequest
{
    public function authorize()
    {
        return backpack_auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(BreakDetails::normalize($this->only('usable_area')));
    }

    public function rules()
    {
        $entry = $this->entry();

        return BreakDetails::rules($this->all(), $this->damagedArea($entry), $entry?->machine_id);
    }

    public function messages()
    {
        return BreakDetails::messages($this->damagedArea($this->entry()));
    }

    private function entry(): ?BrokenGlass
    {
        $id = $this->route('id') ?? $this->input('id');

        return $id ? BrokenGlass::find($id) : null;
    }

    private function damagedArea(?BrokenGlass $entry): ?float
    {
        return $entry?->damaged_area !== null ? (float) $entry->damaged_area : null;
    }
}
