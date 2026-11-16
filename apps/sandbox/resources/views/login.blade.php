<x-layout>
    <x-slot:title>
        Login
    </x-slot:title>
    <p>Sandbox only: no username or password, just click the button to sign in as <strong>admin</strong>.</p>
    @error('login')
        <p style="color: red">{{ $message }}</p>
    @enderror
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <button type="submit" style="margin-left: 0">Login</button>
    </form>
</x-layout>
