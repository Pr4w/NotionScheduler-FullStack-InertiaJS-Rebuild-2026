<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAffiliates extends Model
{
    use HasFactory;

    protected $table = 'user_affiliates';
    protected $primaryKey = 'id';

    protected $guarded = [
        'id',
    ];

    protected $appends = ['affiliate_link']; // This ensures it's included in the JSON response

    public function getAffiliateLinkAttribute()
    {
        return "https://notionscheduler.app/?aff=" . $this->name;
    }
}