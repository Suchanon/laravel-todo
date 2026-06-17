<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;


    /**
     * Mass-assignable attributes. THIS IS SECURITY-CRITICAL — see gotchas.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'is_completed',
        'due_at',
    ];

    /**
     * Type conversion
     * Attribute casting — the modern Laravel 11+ method form (not the old $casts property).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'due_at' => 'datetime',
        ];
    }
}
