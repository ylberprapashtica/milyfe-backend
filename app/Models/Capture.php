<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Capture extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'content',
        'title',
        'slug',
        'tags',
        'user_id',
        'graph_x',
        'graph_y',
        'capture_type_id',
        'capture_status_id',
        'sketch_image',
        'voice_audio',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
        'graph_x' => 'decimal:2',
        'graph_y' => 'decimal:2',
    ];

    /**
     * Get the user that owns the capture.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the capture type.
     */
    public function captureType()
    {
        return $this->belongsTo(CaptureType::class);
    }

    /**
     * Get the capture status.
     */
    public function captureStatus()
    {
        return $this->belongsTo(CaptureStatus::class);
    }

    /**
     * Get notes that this note links to.
     */
    public function linksTo()
    {
        return $this->belongsToMany(
            Capture::class,
            'note_links',
            'source_capture_id',
            'target_capture_id'
        )->withTimestamps()->withPivot('id');
    }

    /**
     * Get notes that link to this note.
     */
    public function linkedFrom()
    {
        return $this->belongsToMany(
            Capture::class,
            'note_links',
            'target_capture_id',
            'source_capture_id'
        )->withTimestamps();
    }

    /**
     * Generate a URL-friendly slug from a title.
     */
    public static function generateSlug(string $title): string
    {
        $slug = Str::slug($title);
        
        // Ensure uniqueness by appending a number if needed
        $originalSlug = $slug;
        $counter = 1;
        
        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Extract title from first line of content if title is empty.
     */
    public static function extractTitleFromContent(string $content): string
    {
        $lines = explode("\n", trim($content));
        $firstLine = trim($lines[0] ?? '');
        
        // Use first line, but limit to 100 characters
        return mb_substr($firstLine, 0, 100);
    }

    /**
     * Parse [[link]] patterns from content.
     *
     * @return array<string> Array of link titles found in content
     */
    public static function parseLinks(string $content): array
    {
        $links = [];
        $pattern = '/\[\[([^\]]+)\]\]/';
        
        if (preg_match_all($pattern, $content, $matches)) {
            $links = array_map('trim', $matches[1]);
        }
        
        return array_unique($links);
    }

    /**
     * Sync bidirectional links based on [[links]] in content.
     */
    public function syncLinks(): void
    {
        // Parse links from content
        $linkTitles = static::parseLinks($this->content);
        
        if (empty($linkTitles)) {
            // No links found, remove all existing links
            $this->linksTo()->sync([]);
            return;
        }
        
        // Generate slugs for all link titles
        $linkSlugs = array_map([static::class, 'generateSlug'], $linkTitles);
        
        // Find captures by title or slug (matching user's captures)
        $linkedCaptures = static::where('user_id', $this->user_id)
            ->where(function ($query) use ($linkTitles, $linkSlugs) {
                $query->whereIn('title', $linkTitles)
                      ->orWhereIn('slug', $linkSlugs);
            })
            ->get();
        
        // Sync the links (this will create/delete as needed)
        $this->linksTo()->sync($linkedCaptures->pluck('id')->toArray());
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug and extract title before saving
        static::saving(function ($capture) {
            // Extract title from content if empty
            if (empty($capture->title) && !empty($capture->content)) {
                $capture->title = static::extractTitleFromContent($capture->content);
            }
            
            // Generate slug from title if empty
            if (empty($capture->slug) && !empty($capture->title)) {
                $capture->slug = static::generateSlug($capture->title);
            }
        });

        // Sync links after saving
        static::saved(function ($capture) {
            $capture->syncLinks();
        });
    }
}
