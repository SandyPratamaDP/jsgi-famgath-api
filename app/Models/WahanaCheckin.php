<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WahanaCheckin extends Model
{
    protected $fillable = ['employee_id', 'wahana', 'checked_in_by'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function checkedInByUser()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
