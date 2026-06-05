<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentViewsModel extends Model
{
    protected $table = 'document_views'; 

    public function docsData()
    {
        return $this->belongsTo(DocsModel::class, 'docs_table_id');
    }
}