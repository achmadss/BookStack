<?php

namespace BookStack\UserGroups\Models;

use BookStack\Activity\Models\Loggable;
use BookStack\App\Model;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Models\Entity;
use BookStack\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Class UserGroup.
 *
 * @property int        $id
 * @property string     $name
 * @property string     $description
 * @property string     $color
 * @property int        $sort_order
 * @property Collection $users
 */
class UserGroup extends Model implements Loggable
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'color', 'sort_order'];

    protected $hidden = ['pivot'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * The users that belong to this user group.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_user')
            ->withPivot('sort_order', 'created_at')
            ->orderBy('user_group_user.sort_order');
    }

    /**
     * Get all content items (shelves and books) associated with this group.
     */
    public function contentItems(): HasMany
    {
        return $this->hasMany(UserGroupContent::class);
    }

    /**
     * Get all shelves accessible by this group via polymorphic relationship.
     */
    public function shelves(): MorphToMany
    {
        return $this->morphedByMany(
            Bookshelf::class,
            'entity',
            'user_group_content',
            'user_group_id',
            'entity_id'
        )->where('user_group_content.entity_type', '=', 'bookshelf');
    }

    /**
     * Get all books accessible by this group via polymorphic relationship.
     */
    public function books(): MorphToMany
    {
        return $this->morphedByMany(
            Book::class,
            'entity',
            'user_group_content',
            'user_group_id',
            'entity_id'
        )->where('user_group_content.entity_type', '=', 'book');
    }

    /**
     * Add a user to this group.
     */
    public function addUser(User $user): void
    {
        if (!$this->users()->where('user_id', $user->id)->exists()) {
            $this->users()->attach($user->id, [
                'sort_order' => $this->users()->count(),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Remove a user from this group.
     */
    public function removeUser(User $user): void
    {
        $this->users()->detach($user->id);
    }

    /**
     * Add content (shelf or book) to this group.
     */
    public function addContent(Entity $entity): void
    {
        $exists = $this->contentItems()
            ->where('entity_id', $entity->id)
            ->where('entity_type', $entity->getMorphClass())
            ->exists();

        if (!$exists) {
            $this->contentItems()->create([
                'entity_id' => $entity->id,
                'entity_type' => $entity->getMorphClass(),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Remove content from this group.
     */
    public function removeContent(Entity $entity): void
    {
        $this->contentItems()
            ->where('entity_id', $entity->id)
            ->where('entity_type', $entity->getMorphClass())
            ->delete();
    }

    /**
     * Get the count of users in this group.
     */
    public function getUsersCount(): int
    {
        return $this->users()->count();
    }

    /**
     * Get the count of content items (shelves + books) in this group.
     */
    public function getContentItemsCount(): int
    {
        return $this->contentItems()->count();
    }

    /**
     * Get descriptive information about this instance for activity logging.
     */
    public function logDescriptor(): string
    {
        return "User Group ({$this->id}) {$this->name}";
    }
}
