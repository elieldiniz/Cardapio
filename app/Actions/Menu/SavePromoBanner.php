<?php

namespace App\Actions\Menu;

use App\Models\PromoBanner;
use App\Models\Restaurant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Saves the promotional banner at the top of the cardápio (RF-16): title up to 70
 * characters, text up to 140, an optional last day and an optional photo. A store has one
 * banner; saving again replaces it, and replacing the photo deletes the old file.
 */
class SavePromoBanner
{
    public const TITLE_LIMIT = 70;

    public const TEXT_LIMIT = 140;

    /**
     * @param  array{title: string, text?: ?string, ends_on?: ?string, is_active?: bool}  $data
     *
     * @throws ValidationException
     */
    public function handle(Restaurant $restaurant, array $data, ?UploadedFile $image = null, bool $removeImage = false): PromoBanner
    {
        $validated = Validator::make(array_merge($data, ['image' => $image]), [
            'title' => ['required', 'string', 'max:'.self::TITLE_LIMIT],
            'text' => ['nullable', 'string', 'max:'.self::TEXT_LIMIT],
            'ends_on' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'title.required' => 'Dê um título ao banner.',
            'title.max' => 'O título passa de '.self::TITLE_LIMIT.' caracteres.',
            'text.max' => 'O texto passa de '.self::TEXT_LIMIT.' caracteres.',
            'ends_on.date' => 'Data inválida.',
        ])->validate();

        $banner = PromoBanner::query()->firstOrNew(['restaurant_id' => $restaurant->id]);
        $previousImage = $banner->image_path;
        $path = $previousImage;

        if ($image !== null) {
            $path = $image->store("banners/{$restaurant->id}", 'public');
        } elseif ($removeImage) {
            $path = null;
        }

        $banner->fill([
            'title' => trim($validated['title']),
            'text' => filled($validated['text'] ?? null) ? trim($validated['text']) : null,
            'ends_on' => filled($validated['ends_on'] ?? null) ? $validated['ends_on'] : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'image_path' => $path,
        ])->save();

        if ($previousImage !== null && $previousImage !== $path) {
            Storage::disk('public')->delete($previousImage);
        }

        return $banner;
    }

    public function remove(Restaurant $restaurant): void
    {
        $banner = PromoBanner::query()->where('restaurant_id', $restaurant->id)->first();

        if ($banner === null) {
            return;
        }

        if ($banner->image_path !== null) {
            Storage::disk('public')->delete($banner->image_path);
        }

        $banner->delete();
    }
}
