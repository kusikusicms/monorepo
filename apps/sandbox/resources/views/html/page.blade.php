{{--
    Rendered by App\Http\Controllers\WebsiteController::page() for entities with the "page" view
    requested as HTML ("{format}.{view}" → "html.page").
    Available: $entity (contents in $lang) and $lang.
    Missing content fields are empty strings.
--}}
<x-html.layout :title="$entity->contents->title" :lang="$lang">
    <h1>{{ $entity->contents->title }}</h1>

    @if ($entity->contents->body)
        <p>{{ $entity->contents->body }}</p>
    @endif
</x-html.layout>
