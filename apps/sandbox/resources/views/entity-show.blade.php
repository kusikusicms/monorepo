<x-layout>
    <x-slot:title>
        Entity: {{ $entity->contents->title }}
    </x-slot:title>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" style="margin-left: 0">Logout</button>
    </form>
    <pre>{{ $entity->toJSON(JSON_PRETTY_PRINT) }}</pre>
    <x-edit :entity="$entity"></x-edit>
    <x-create :parent-entity-id="$entity->id"></x-create>
    <x-delete :id="$entity->id"></x-delete>
    <hr>
    <h2>Children</h2>
    <ol>
        @foreach($children as $e)
            <li><x-entity :entity="$e"></x-entity></li>
        @endforeach
    </ol>

    <h2>Ancestors</h2>
    <ol>
        @foreach($ancestors as $e)
            <li><x-entity :entity="$e"></x-entity></li>
        @endforeach
    </ol>
</x-layout>
