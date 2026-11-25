<?php

namespace App\Actions\Tool;

use App\Models\Tool\Category;
use App\Models\User\User;

class SuggestCategory
{
    /**
     * Add a category suggested by the user. An admin's category is approved
     * right away; anybody else's waits for an admin.
     *
     * @param  array{name: string, description?: ?string}  $data
     */
    public function handle(User $user, array $data): Category
    {
        $category = new Category($data);
        $category->suggestedBy()->associate($user);
        $category->approved_at = $user->isAdmin() ? now() : null;
        $category->save();

        return $category;
    }
}
