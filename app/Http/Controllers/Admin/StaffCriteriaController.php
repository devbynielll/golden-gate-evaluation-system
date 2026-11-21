<?php

namespace App\Http\Controllers\Admin;

use App\Models\{StaffCriteriaCategory, StaffCriteriaQuestion};
use Illuminate\Http\{RedirectResponse, Request};
use Inertia\Response;

// Reuse criteria behavior and UI, with explicit bindings to the separate staff tables.
class StaffCriteriaController extends CriteriaController
{
    public function index(Request $request): Response { return $this->staff($request); }
    public function storeStaffCategory(Request $request): RedirectResponse { return parent::storeCategory($request, 'staff'); }
    public function updateStaffCategory(Request $request, StaffCriteriaCategory $staffCategory): RedirectResponse { return parent::updateCategory($request, 'staff', $staffCategory); }
    public function destroyStaffCategory(Request $request, StaffCriteriaCategory $staffCategory): RedirectResponse { return parent::destroyCategory($request, 'staff', $staffCategory); }
    public function storeStaffQuestion(Request $request, StaffCriteriaCategory $staffCategory): RedirectResponse { return parent::storeQuestion($request, 'staff', $staffCategory); }
    public function updateStaffQuestion(Request $request, StaffCriteriaCategory $staffCategory, StaffCriteriaQuestion $staffQuestion): RedirectResponse { return parent::updateQuestion($request, 'staff', $staffCategory, $staffQuestion); }
    public function destroyStaffQuestion(Request $request, StaffCriteriaCategory $staffCategory, StaffCriteriaQuestion $staffQuestion): RedirectResponse { return parent::destroyQuestion($request, 'staff', $staffCategory, $staffQuestion); }
}
