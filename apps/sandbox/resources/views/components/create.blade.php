<h3>Create</h3>
<form action="/entity" method="POST">
    @csrf
    <label>
        <span>ID</span>
        <input type="text" name="id" placeholder="Optional, but not devil">
    </label>
    <label>
        <span>Title</span>
        <input type="text" name="title" placeholder="Title" required>
    </label>
    <input type="hidden" name="parent_entity_id" value="{{ $parentEntityId }}">
    <button type="submit">Create</button>
</form>
