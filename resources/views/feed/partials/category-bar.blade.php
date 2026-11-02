<nav class="feed-categories" data-category-bar aria-label="Categorias">
    @foreach ($categories as $category)
        <button
            type="button"
            class="feed-category"
            data-category="{{ $category['id'] }}"
            @if ($category['url'] ?? null) data-category-url="{{ $category['url'] }}" @endif
            aria-pressed="{{ $category['id'] === $activeCategoryId ? 'true' : 'false' }}"
        >@if ($category['icon'] ?? null)<span aria-hidden="true">{{ $category['icon'] }}</span> @endif{{ $category['name'] }}</button>
    @endforeach
</nav>
