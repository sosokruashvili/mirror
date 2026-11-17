<?php

namespace App\Support\BrokenGlass;

use App\Models\BrokenGlass;
use App\Models\Machine;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * Validates the details asked for when a glass breaks: machine, the stage at
 * which the damage was found, usable m² and the comment.
 *
 * Shared by the team's "გატყდა" dialogs (TeamOrderController) and the edit form on
 * the Broken Glasses page (BrokenGlassRequest), so both enforce the same rules:
 *  - every field except the comment is required; usable m² may be 0;
 *  - usable m² cannot exceed the broken sheet's area;
 *  - a machine flagged requires_comment ("სხვა") makes the comment required.
 *
 * Messages are Georgian on purpose: the team pages are Georgian-only.
 */
class BreakDetails
{
    /**
     * @param  array<string, mixed>  $input
     * @param  int|null  $currentMachineId  a machine already on the record, accepted even if since switched off
     * @return array{machine_id: int, detected_stage_id: int, usable_area: float, description: ?string}
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validate(array $input, ?float $damagedArea, ?int $currentMachineId = null): array
    {
        $input = self::normalize($input);

        $data = Validator::make(
            $input,
            self::rules($input, $damagedArea, $currentMachineId),
            self::messages($damagedArea)
        )->validate();

        return [
            'machine_id' => (int) $data['machine_id'],
            'detected_stage_id' => (int) $data['detected_stage_id'],
            'usable_area' => round((float) $data['usable_area'], 4),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
        ];
    }

    /**
     * Accept a decimal comma ("0,5") and trim the comment.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        if (isset($input['usable_area']) && is_string($input['usable_area'])) {
            $input['usable_area'] = str_replace(',', '.', trim($input['usable_area']));
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, mixed>>
     */
    public static function rules(array $input, ?float $damagedArea, ?int $currentMachineId = null): array
    {
        $machineRule = Rule::exists('machines', 'id')->where(function ($query) use ($currentMachineId) {
            $query->where('is_active', true);
            if ($currentMachineId) {
                $query->orWhere('id', $currentMachineId);
            }
        });

        $machineId = $input['machine_id'] ?? null;
        $machine = is_numeric($machineId) ? Machine::find((int) $machineId) : null;

        return [
            'machine_id' => ['required', 'integer', $machineRule],
            'detected_stage_id' => ['required', 'integer', Rule::in(BrokenGlass::detectionStages()->pluck('id')->all())],
            'usable_area' => ['required', 'numeric', 'min:0', 'max:' . self::maxUsable($damagedArea)],
            'description' => [$machine?->requires_comment ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(?float $damagedArea): array
    {
        $max = self::formatArea(self::maxUsable($damagedArea));

        return [
            'machine_id.required' => 'აირჩიე დანადგარი.',
            'machine_id.integer' => 'აირჩიე დანადგარი.',
            'machine_id.exists' => 'ეს დანადგარი სიაში აღარ არის. აირჩიე სხვა.',
            'detected_stage_id.required' => 'აირჩიე ეტაპი, რომელზეც დაზიანება აღმოაჩინეს.',
            'detected_stage_id.integer' => 'აირჩიე ეტაპი, რომელზეც დაზიანება აღმოაჩინეს.',
            'detected_stage_id.in' => 'აირჩიე ეტაპი სიიდან.',
            'usable_area.required' => 'ჩაწერე გამოსადეგი მ². თუ არაფერია გამოსადეგი, ჩაწერე 0.',
            'usable_area.numeric' => 'გამოსადეგი მ² რიცხვი უნდა იყოს.',
            'usable_area.min' => 'გამოსადეგი მ² უარყოფითი ვერ იქნება.',
            'usable_area.max' => "გამოსადეგი მ² ფურცლის ფართობზე ({$max} მ²) მეტი ვერ იქნება.",
            'description.required' => 'ამ დანადგარისთვის კომენტარი სავალდებულოა.',
            'description.max' => 'კომენტარი ძალიან გრძელია.',
        ];
    }

    /**
     * Area (m²) of one sheet of the given size in cm, rounded the way it is stored.
     */
    public static function sheetArea($width, $height): ?float
    {
        if (! is_numeric($width) || ! is_numeric($height) || $width <= 0 || $height <= 0) {
            return null;
        }

        return round(((float) $width / 100) * ((float) $height / 100), 4);
    }

    public static function formatArea($area): string
    {
        return rtrim(rtrim(number_format((float) $area, 4, '.', ''), '0'), '.') ?: '0';
    }

    private static function maxUsable(?float $damagedArea): float
    {
        return max(0, round((float) $damagedArea, 4));
    }
}
