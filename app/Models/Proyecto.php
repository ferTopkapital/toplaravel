<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * Proyecto inmobiliario (`proyecto`).
 *
 * La tabla tiene **149 columnas** y una PK compuesta
 * `(proyectoId, datosGeneralesCompleta)`. Como `proyectoId` es AUTO_INCREMENT
 * es único por sí solo, así que declararlo como llave funciona; no se debe
 * "arreglar" la PK en la base compartida.
 *
 * Ojo con el ancho: nunca hacer `SELECT *` en listados. Usa el scope
 * `paraTarjeta()`, que trae sólo lo que la tarjeta necesita.
 */
class Proyecto extends Model
{
    protected $table = 'proyecto';

    protected $primaryKey = 'proyectoId';

    public $timestamps = false;

    /** Etapas del proyecto. Los valores están persistidos: no se renumeran. */
    public const ETAPA_EN_FONDEO = 1;
    public const ETAPA_FONDEADO = 2;
    public const ETAPA_CONSTRUCCION = 3;
    public const ETAPA_FINALIZADO = 4;
    public const ETAPA_NO_FONDEADO = 5;

    /**
     * Etiquetas tal como las ve el cliente. "En Proceso" y "Finalizados"
     * no coinciden con el nombre de la constante a propósito: son los
     * textos que la app Yii2 ya muestra y que el negocio reconoce.
     */
    public const ETAPAS = [
        self::ETAPA_EN_FONDEO => 'En fondeo',
        self::ETAPA_FONDEADO => 'Fondeado',
        self::ETAPA_CONSTRUCCION => 'En proceso',
        self::ETAPA_FINALIZADO => 'Finalizado',
        self::ETAPA_NO_FONDEADO => 'No fondeado',
    ];

    protected function casts(): array
    {
        return [
            'proyectoId' => 'integer',
            'etapaId' => 'integer',
            // Arreglo JSON de nombres de archivo en S3, no una sola imagen.
            'imagenes' => 'array',
            'visible' => 'boolean',
            'verificacionCompleta' => 'boolean',
            'mostrarLanding' => 'integer',
            'monto' => 'decimal:2',
            'minimoFondeo' => 'decimal:2',
            'plazo' => 'integer',
            'periodoPago' => 'integer',
            'fechaInicio' => 'date',
            'fechaFinal' => 'date',
            'createdAt' => 'datetime',
            'modifiedAt' => 'datetime',
        ];
    }

    public function campanas(): HasMany
    {
        return $this->hasMany(Campana::class, 'proyectoId', 'proyectoId');
    }

    /**
     * Campaña vigente. Un proyecto puede fondearse en varias vueltas, y la
     * que manda es siempre la última.
     */
    public function campanaActual(): HasOne
    {
        return $this->hasOne(Campana::class, 'proyectoId', 'proyectoId')
            ->orderByDesc('campanaId');
    }

    public function inversiones(): HasMany
    {
        return $this->hasMany(SolicitudInversion::class, 'proyectoId', 'proyectoId');
    }

    public function noticias(): HasMany
    {
        return $this->hasMany(Noticia::class, 'proyectoId', 'proyectoId');
    }

    public function promotor()
    {
        return $this->belongsTo(Promotor::class, 'promotorId', 'promotorId');
    }

    /**
     * Sólo lo que la tarjeta del listado necesita.
     *
     * Con 149 columnas, un `SELECT *` sobre una lista de proyectos mueve una
     * cantidad absurda de datos —incluidos campos de texto largo— para pintar
     * seis valores.
     */
    public function scopeParaTarjeta($query)
    {
        return $query->select([
            'proyectoId',
            'nombre',
            'etapaId',
            'visible',
            'resumen',
            'direccion',
            // `imagenes` guarda la galería del proyecto, no una sola imagen.
            'imagenes',
            'plazo',
            'monto',
            'minimoFondeo',
            'fechaInicio',
            'fechaFinal',
        ]);
    }

    /** Proyectos publicables: verificados y marcados como visibles. */
    public function scopePublicos($query)
    {
        return $query->where('verificacionCompleta', 1)->where('visible', 1);
    }

    public function etapa(): string
    {
        return self::ETAPAS[$this->etapaId] ?? 'Sin etapa';
    }

    /**
     * URL de la primera imagen del proyecto, o null si no tiene.
     *
     * Devolver null en vez de una imagen genérica es deliberado: la tarjeta
     * dibuja su propio marcador y así se distingue "este proyecto no tiene
     * foto" de "la foto no cargó".
     */
    public function portada(): ?string
    {
        $imagenes = $this->imagenes;

        if (! is_array($imagenes) || $imagenes === []) {
            return null;
        }

        return static::urlDeS3((string) reset($imagenes));
    }

    /**
     * URL temporal firmada de un objeto del bucket, o null si no se puede.
     *
     * OJO: aunque `topkapital-env.conf` declara `S3_ACL=public-read`, los
     * objetos del bucket **no** son legibles públicamente — la URL directa
     * responde 403 (comprobado). Por eso hay que firmarlas, igual que hace la
     * app Yii2 con `getPresignedUrl()`.
     *
     * Devuelve null en vez de reventar cuando faltan credenciales: sin ellas
     * la tarjeta dibuja su marcador y la pantalla sigue siendo usable. Una
     * imagen que no carga no debe tumbar el panel del cliente.
     */
    public static function urlDeS3(string $archivo, int $minutos = 30): ?string
    {
        $archivo = ltrim($archivo, '/');

        if ($archivo === '' || blank(config('filesystems.disks.s3.key'))) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($archivo, now()->addMinutes($minutos));
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
