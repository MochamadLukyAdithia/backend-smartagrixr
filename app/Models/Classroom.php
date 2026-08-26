<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
 
class Classroom extends Model
{
    use HasFactory, SoftDeletes;
 
    protected $fillable = [
        'teacher_id', 'name', 'description',
        'subject', 'invite_code', 'is_active',
    ];
 
    protected $casts = ['is_active' => 'boolean'];
 
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
 
    // n:n siswa
    public function students()
    {
        return $this->belongsToMany(User::class, 'classroom_user')
            ->withPivot('role', 'enrolled_at')
            ->withTimestamps();
    }
 
    // semua post di kelas ini (feed)
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
 
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
 
    public function materials()
    {
        return $this->hasMany(Material::class);
    }
 
    public function isTeacher(int $userId): bool
    {
        return $this->teacher_id === $userId;
    }
 
    public function isStudent(int $userId): bool
    {
        return $this->students()->where('user_id', $userId)->exists();
    }
 
    public function isMember(int $userId): bool
    {
        return $this->isTeacher($userId) || $this->isStudent($userId);
    }
}