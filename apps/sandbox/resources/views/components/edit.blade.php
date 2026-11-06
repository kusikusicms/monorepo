<h3>Edit</h3>
<form action="/entity/{{$entity->id}}" method="POST">
    @csrf
    @method('PATCH')
    <label>
        <span>Title</span>
        <input type="text" name="title" placeholder="Title" required value="{{$entity->contents->title}}">
    </label>
    <label>
        <span>Slug</span>
        <input type="text" name="slug" placeholder="slug" value="{{$entity->contents->slug}}">
    </label>
    <label>
        <span>Description</span>
        <textarea name="description" placeholder="Description">{{$entity->contents->description}}</textarea>
    </label>
    <button type="submit">Edit</button>
</form>
