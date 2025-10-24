<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'email',
    ];

    // get parent group
    public function parent()
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    // get children groups (one level down)
    public function children()
    {
        // return $this->hasMany(Group::class, 'parent_id');
        return $this->hasMany(Group::class, 'parent_id');
    }

    // get children groups (all levels)
    public function getAllChildren()
    {
        $children = $this->children;
        foreach ($children as $child) {
            $children = $children->merge($children->getAllChildren($child));
        }

        return $children;
    }

    // get the level of the group
    public function level()
    {
        $level = 0;
        $parent = $this->parent;
        while ($parent) {
            $level++;
            $parent = $parent->parent;
        }

        return $level;
    }

    /* get the users */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_groups', 'group_id', 'user_id');
    }

    public function ticketTypes()
    {
        return $this->belongsToMany(TicketType::class, 'ticket_type_group', 'group_id', 'ticket_type_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function ticketsWithUser()
    {
        return $this->hasMany(Ticket::class)->with([
            'user' => function ($query) {
                $query->select(['id', 'name', 'surname', 'is_admin', 'is_company_admin', 'is_deleted']); // Specify the columns you want to include
            },
            'user.companies:id,name',
        ]);
    }

    /**
     * Assignment history records related to this group.
     */
    public function assignmentHistoryRecords()
    {
        return $this->hasMany(TicketAssignmentHistoryRecord::class, 'group_id');
    }

    // public function allTickets()
    // {
    //     return $this->tickets()->where('group_id', $this->id);
    // }
}
