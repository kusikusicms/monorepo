<?php

namespace KusikusiCMS\Acl\Api\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use KusikusiCMS\Acl\Acl;

trait ResolvesUsers
{
    /**
     * A query on the acl user model with its roles of the acl guard eager loaded.
     *
     * @return Builder<Model>
     */
    protected function users(): Builder
    {
        /** @var class-string<Model> $model */
        $model = Acl::userModel();
        $guard = (string) config('kusikusicms.acl.guard_name', 'web');

        return $model::query()->with(['roles' => fn ($query) => $query->where('guard_name', $guard)->orderBy('name')]);
    }

    protected function findUser(int|string $id): Model
    {
        return $this->users()->findOrFail($id);
    }
}
