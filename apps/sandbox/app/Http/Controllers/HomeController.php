<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use KusikusiCMS\Models\Entity;

class HomeController extends Controller
{
    /**
     * Display the welcome view.
     */
    public function index(): View
    {
        $entities = Entity::withContents(['lang' => 'en'])
            ->where('parent_entity_id', null)
            ->currentlyLive()->get();

        return view('welcome', ['entities' => $entities]);
    }
}
