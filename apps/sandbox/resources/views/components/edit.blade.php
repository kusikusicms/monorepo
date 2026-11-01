<h3>Edit</h3>
<form action="/entity/{{$id}}" method="POST">
    @csrf
    @method('PATCH')
    <label>
        <span>Title</span>
        <input type="text" name="title" placeholder="Title" required><br>
    </label>
    <label>
        <span>Description</span>
        <textarea name="description" placeholder="Description"></textarea>
    </label>
    <button type="submit">Edit</button>
</form>
