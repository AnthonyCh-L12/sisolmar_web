<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo CursoNew
 *
 * Representa la nueva tabla maestra de cursos: sw_cursos_new.
 *
 * Notas de migración:
 * - CODIGO es la PK identity y conserva el valor del antiguo sw_cursos.codigo,
 *   por lo que las tablas hijas (programaciones, exámenes, matrículas) siguen
 *   siendo válidas sin remapear.
 * - SW_CURS_COD es el código de negocio (equivalente a sw_cursos.codigo_curso).
 * - SW_CURS_AV_COD guarda el código Moodle del curso.
 */
class CursoNew extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'sw_cursos_new';

    protected $primaryKey = 'CODIGO';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'SW_CURS_COD',
        'SW_CURS_NOMBRE',
        'SW_CURS_DESCRIPCION',
        'SW_CURS_TIPO_COD',
        'SW_CURS_RESP_COD',
        'SW_CURS_PLAN_CAPAC_COD',
        'SW_CURS_SIST_GESTION_COD',
        'SW_CURS_AREA_COD',
        'SW_CURS_SUCURSAL_COD',
        'SW_CURS_CANT_PERIOD',
        'SW_CURS_AV_COD',
        'SW_CURS_DIRIGIDO_COD',
        'SW_CURS_PERIODICIDAD',
        'SW_CURS_EVALUACION',
        'habilitado',
        'creado_por',
        'modificado_por',
        'fecha_creacion',
        'fecha_modificacion',
        'SW_CURS_CLIENTE_COD',
    ];

    protected $casts = [
        'SW_CURS_TIPO_COD' => 'integer',
        'SW_CURS_RESP_COD' => 'integer',
        'SW_CURS_PLAN_CAPAC_COD' => 'integer',
        'SW_CURS_SIST_GESTION_COD' => 'integer',
        'SW_CURS_AREA_COD' => 'integer',
        'SW_CURS_SUCURSAL_COD' => 'integer',
        'SW_CURS_CANT_PERIOD' => 'integer',
        'SW_CURS_AV_COD' => 'integer',
        'SW_CURS_DIRIGIDO_COD' => 'integer',
        'SW_CURS_PERIODICIDAD' => 'boolean',
        'SW_CURS_EVALUACION' => 'boolean',
        'habilitado' => 'boolean',
        'fecha_creacion' => 'datetime',
        'fecha_modificacion' => 'datetime',
    ];

    /**
     * Programaciones del curso.
     * Se mantiene la FK histórica cod_curso -> CODIGO.
     */
    public function programaciones()
    {
        return $this->hasMany(CursoProgramacion::class, 'cod_curso', 'CODIGO');
    }

    /**
     * Examen asociado al curso.
     */
    public function examen()
    {
        return $this->hasOne(ExamenCurso::class, 'cod_cursos', 'CODIGO');
    }

    public function scopeHabilitados($query)
    {
        return $query->where('habilitado', 1);
    }
}
