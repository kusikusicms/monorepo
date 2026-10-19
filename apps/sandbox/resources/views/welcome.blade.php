<x-layout>
    <x-slot:title>
        KusikusiCSM Sandbox
    </x-slot>
    <h2>Entities</h2>
    <x-create parent-entity-id="" />
    <h3>Root entities</h3>
    <ul>
        @foreach($entities as $e)
            <li><x-entity :entity="$e"></x-entity></li>
        @endforeach
    </ul>
</x-layout>
