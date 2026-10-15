<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Support\RestaurantQr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    /**
     * The printable table display, with the saved model, colors and texts.
     */
    public function print(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        return view('panel.qr-print', [
            'restaurant' => $restaurant,
            'svg' => RestaurantQr::svg($restaurant),
            'settings' => RestaurantQr::settings($restaurant),
            'table' => Str::limit((string) $request->query('mesa'), 10, ''),
        ]);
    }

    /**
     * The QR code as a vector file, which print shops ask for.
     */
    public function svg(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;

        return response('<?xml version="1.0" encoding="UTF-8"?>'.RestaurantQr::svg($restaurant), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-code-'.$restaurant->slug.'.svg"',
        ]);
    }
}
