<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The privacy policy page. With `?loja={slug}` it also shows what that store does with the
 * data of an order (RF-06f, RNF-08); without it, or with an unknown store, the general page.
 */
class PrivacyController extends Controller
{
    public function __invoke(Request $request): View
    {
        $slug = $request->query('loja');

        $restaurant = is_string($slug) && $slug !== ''
            ? Restaurant::query()->where('slug', $slug)->first()
            : null;

        if ($restaurant?->isSuspended()) {
            $restaurant = null;
        }

        return view('marketing.legal.privacy', [
            'store' => $restaurant,
            'storeWhatsapp' => $restaurant?->orderSettings()->whatsapp_number,
        ]);
    }
}
