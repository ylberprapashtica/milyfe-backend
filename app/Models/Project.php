<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'graph_x',
        'graph_y',
        'graph_width',
        'graph_height',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'graph_x' => 'decimal:2',
        'graph_y' => 'decimal:2',
        'graph_width' => 'decimal:2',
        'graph_height' => 'decimal:2',
    ];

    /**
     * Get the user that owns the project.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the captures in this project.
     */
    public function captures()
    {
        return $this->hasMany(Capture::class);
    }
}
