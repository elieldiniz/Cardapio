<nav class="feed-categories" data-category-bar aria-label="Categorias">
    @foreach ($categories as $category)
        <button
            type="button"
            class="feed-category"
            data-category="{{ $category['id'] }}"
            @if ($category['url'] ?? null) data-category-url="{{ $category['url'] }}" @endif
            aria-pressed="{{ $category['id'] === $activeCategoryId ? 'true' : 'false' }}"
        >{{ $category['name'] }}</button>
    @endforeach
</nav>
