<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reporting\AdminDashboardSummary;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(AdminDashboardSummary $summary): Response
    {
        return Inertia::render('Admin/Dashboard', $summary->toArray());
    }
}
