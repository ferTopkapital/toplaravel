<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Noticia o avance de obra de un proyecto (`noticia`). */
class Noticia extends Model
{
    protected $table = 'noticia';

    protected $primaryKey = 'noticiaId';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'noticiaId' => 'integer',
            'proyectoId' => 'integer',
            'createdAt' => 'datetime',
            'modifiedAt' => 'datetime',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyectoId', 'proyectoId');
    }

    public function urlPdf(): ?string
    {
        return blank($this->archivoPdf) ? null : Proyecto::urlDeS3((string) $this->archivoPdf);
    }
}
