<h3>Create</h3>
<form action="/entity" method="POST">
    @csrf
    <label>
        <span>ID</span>
        <input type="text" name="id" placeholder="Optional, but not devil">
    </label>
    <label>
        <span>Model</span>
        <select name="model">
            <option value="Page">Page</option>
            <option value="Section">Section</option>
            <option value="Home">Home</option>
            <option value="Entity">Entity</option>
        </select>
    </label>
    <label>
        <span>Title</span>
        <input type="text" name="title" placeholder="Title" required>
    </label>
    <label>
        <span>Slug</span>
        <input type="text" name="slug" placeholder="slug">
    </label>
    <input type="hidden" name="parent_entity_id" value="{{ $parentEntityId }}">
    <button type="submit">Create</button>
</form>
<script>
    (function (script) {
        const form = script.previousElementSibling;
        const title = form.querySelector('input[name=title]');
        const slug = form.querySelector('input[name=slug]');
        let slugTouched = false;

        const slugify = (value) => value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        slug.addEventListener('input', () => {
            slugTouched = slug.value !== '';
        });
        title.addEventListener('input', () => {
            if (!slugTouched) {
                slug.value = slugify(title.value);
            }
        });
    })(document.currentScript);
</script>
