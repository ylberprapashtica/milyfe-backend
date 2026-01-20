<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoteLink extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'source_capture_id',
        'target_capture_id',
    ];

    /**
     * Get the source capture (the note that links).
     */
    public function sourceCapture()
    {
        return $this->belongsTo(Capture::class, 'source_capture_id');
    }

    /**
     * Get the target capture (the note being linked to).
     */
    public function targetCapture()
    {
        return $this->belongsTo(Capture::class, 'target_capture_id');
    }
}
