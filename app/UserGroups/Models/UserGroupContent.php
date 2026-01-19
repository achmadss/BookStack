<?php

namespace BookStack\UserGroups\Models;

use BookStack\App\Model;
use BookStack\Entities\Models\Entity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class UserGroupContent.
 * Represents the association between a user group and content (shelves/books).
 *
 * @property int    $id
 * @property int    $user_group_id
 * @property int    $entity_id
 * @property string $entity_type
 */
class UserGroupContent extends Model
{
    public $timestamps = false;

    protected $table = 'user_group_content';

    protected $fillable = ['user_group_id', 'entity_id', 'entity_type'];

    protected $dates = ['created_at'];

    /**
     * Get the user group that owns this content association.
     */
    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class);
    }

    /**
     * Get the entity (shelf or book) associated with this group.
     */
    public function entity(): MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }
}
