<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareDocs extends Model
{
    protected $table = 'share_docs'; 

    public function docsData()
    {
        return $this->belongsTo(DocsModel::class, 'docs_table_id');
    }
}