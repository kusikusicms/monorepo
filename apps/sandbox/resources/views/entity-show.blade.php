<x-layout>
    <x-slot:title>
        Entity: {{ $entity1->contents->title }}
    </x-slot:title>
    <pre>{{ $entity1->toJSON(JSON_PRETTY_PRINT) }}</pre>
    <x-create :parent-entity-id="$entity1->id"></x-create>
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
