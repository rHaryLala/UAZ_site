<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;

class ContactController extends Controller
{
    public function __invoke(): View
    {
        return view('contact', ['site' => SiteSetting::current()]);
    }
}
