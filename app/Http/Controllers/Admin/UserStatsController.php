<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

/**
 * User-focused stats dashboard (widgets about authors / operators).
 */
class UserStatsController extends Controller
{
    /**
     * Show the User Stats dashboard page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('admin.user-stats', [
            'title' => 'User Stats',
        ]);
    }
}
