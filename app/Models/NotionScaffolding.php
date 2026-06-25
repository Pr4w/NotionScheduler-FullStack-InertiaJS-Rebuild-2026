<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotionScaffolding extends Model
{
    // Define the table name for this model
    protected $table = 'notion_scaffolding';
    protected $primaryKey = 'id';
    
}