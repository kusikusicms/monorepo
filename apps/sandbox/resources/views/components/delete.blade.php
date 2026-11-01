<h3>Delete</h3>
<form action="/entity/{{$id}}" method="POST">
    @csrf
    @method('DELETE')
    <button type="submit" style="color: red">Delete</button>
</form>
