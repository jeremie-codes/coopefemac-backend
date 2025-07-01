<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'description'];

    public function roles() {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    public function users() {
        return $this->belongsToMany(User::class, 'user_permission');
    }
}
