<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ColumnVisibilityController extends Controller
{
    /**
     * Persist the current user's column-visibility selection for one CRUD list.
     */
    public function update(Request $request): JsonResponse
    {
        $user = backpack_user();
        abort_unless($user, 401);

        $validated = $request->validate([
            'table' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9\/_-]+$/'],
            'columns' => ['required', 'array', 'max:80'],
        ]);

        $columns = [];
        foreach ($validated['columns'] as $name => $visible) {
            if (! is_string($name) || ! preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
                continue;
            }

            $columns[$name] = filter_var($visible, FILTER_VALIDATE_BOOLEAN);
        }

        $user->setColumnVisibilityFor($validated['table'], $columns);
        $user->save();

        return response()->json(['ok' => true]);
    }
}
