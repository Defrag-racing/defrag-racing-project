<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\BundleCategory;

class BundlesController extends Controller
{
    public function index (Request $request, $id = -1, $slug='') {
        $categories = BundleCategory::with('bundles')->get();
    
        return Inertia::render('Bundles')
            ->with('categories', $categories)
            ->with('id', intval($id));
    }
}
