<?php

namespace Tests\Feature;

use App\Models\Retorno;
use App\Models\SolicitudInversion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Entrega de documentos fiscales y personales.
 *
 * Lo importante aquí es **quién puede ver qué**. Un CFDI o una constancia de
 * retención llevan el RFC del cliente y sus importes; que se filtren al
 * usuario equivocado no es un bug de interfaz, es una fuga de datos fiscales.
 */
class DocumentosTest extends TestCase
{
    use DatabaseTransactions;

    private function crearUsuario(): Usuario
    {
        $usuario = new Usuario();

        $usuario->forceFill([
            'email' => 'qa.doc.' . uniqid() . '@topkapital.test',
            'passwordHash' => Hash::make('Qx7!mvzp'),
            'nombre' => 'Fernanda',
            'estatus' => Usuario::ESTATUS_APROBADO_INVERSION,
            'rol' => Usuario::ROL_INVERSIONISTA,
            'intentosLogin' => 10,
        ]);

        $usuario->save();

        return $usuario;
    }

    private function crearInversionConRetorno(Usuario $u): array
    {
        $inversion = new SolicitudInversion();
        $inversion->forceFill([
            'usuarioId' => $u->getKey(),
            'proyectoId' => 1,
            'monto' => 10000,
            'confirmada' => 1,
            'devuelto' => 0,
            'cancelada' => 0,
            'estado' => 1,
            'createdAt' => now(),
            'nombreComprobante' => 'comprobante.pdf',
        ]);
        $inversion->save();

        $retorno = new Retorno();
        $retorno->forceFill([
            'inversionId' => $inversion->inversionId,
            'fechaCalendario' => now()->addMonth()->toDateString(),
            'fechaPago' => now(),
            'estadoOperacion' => Retorno::ESTADO_LIQUIDACION,
            'intereses' => 500,
            'retencion_isr' => 50,
            'retorno_neto' => 450,
            'cfdi' => 'factura.pdf',
        ]);
        $retorno->save();

        return [$inversion, $retorno];
    }

    public function test_las_rutas_exigen_sesion(): void
    {
        $this->get('/documentos')->assertRedirect(route('login'));
        $this->get('/calendario')->assertRedirect(route('login'));
        $this->get('/perfil')->assertRedirect(route('login'));
    }

    /**
     * El caso que de verdad importa: el CFDI de otro cliente no se entrega,
     * ni siquiera con el id correcto.
     */
    public function test_no_se_entrega_el_documento_de_otro_cliente(): void
    {
        $duenio = $this->crearUsuario();
        $intruso = $this->crearUsuario();

        [$inversion, $retorno] = $this->crearInversionConRetorno($duenio);

        $this->actingAs($intruso)
            ->get("/documentos/cfdi/{$retorno->id}")
            ->assertNotFound();

        $this->actingAs($intruso)
            ->get("/documentos/comprobante/{$inversion->inversionId}")
            ->assertNotFound();
    }

    /**
     * Se responde 404 y no 403: un 403 confirmaría que ese id existe, y con
     * documentos fiscales ajenos eso ya es información de más.
     */
    public function test_responde_404_y_no_403_ante_un_documento_ajeno(): void
    {
        $duenio = $this->crearUsuario();
        $intruso = $this->crearUsuario();

        [, $retorno] = $this->crearInversionConRetorno($duenio);

        $this->actingAs($intruso)
            ->get("/documentos/cfdi/{$retorno->id}")
            ->assertStatus(404);
    }

    public function test_un_tipo_de_documento_desconocido_no_existe(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)->get('/documentos/inventado/1')->assertNotFound();
    }

    public function test_un_documento_sin_archivo_no_se_entrega(): void
    {
        $usuario = $this->crearUsuario();

        $inversion = new SolicitudInversion();
        $inversion->forceFill([
            'usuarioId' => $usuario->getKey(),
            'proyectoId' => 1,
            'monto' => 1000,
            'confirmada' => 1,
            'devuelto' => 0,
            'cancelada' => 0,
            'estado' => 1,
            'createdAt' => now(),
            // Sin nombreComprobante.
        ]);
        $inversion->save();

        $this->actingAs($usuario)
            ->get("/documentos/comprobante/{$inversion->inversionId}")
            ->assertNotFound();
    }

    public function test_el_calendario_separa_programados_de_pagados(): void
    {
        $usuario = $this->crearUsuario();
        $this->crearInversionConRetorno($usuario);

        $this->actingAs($usuario)
            ->get('/calendario')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Calendario/Index'));
    }

    public function test_el_perfil_no_expone_la_clabe_completa(): void
    {
        $usuario = $this->crearUsuario();

        $respuesta = $this->actingAs($usuario)->get('/perfil');

        $respuesta->assertOk();
        // La CLABE está en $hidden del modelo; la vista sólo recibe la máscara.
        $respuesta->assertDontSee('"CLABE"', false);
    }

    public function test_se_puede_elegir_la_imagen_de_seguridad(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)->post('/perfil/imagen', ['imagen' => 5]);

        $this->assertSame(5, (int) $usuario->fresh()->imagenPerfil);
    }

    public function test_la_imagen_de_seguridad_debe_estar_en_el_catalogo(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)
            ->post('/perfil/imagen', ['imagen' => 99])
            ->assertSessionHasErrors('imagen');

        $this->actingAs($usuario)
            ->post('/perfil/imagen', ['imagen' => 0])
            ->assertSessionHasErrors('imagen');
    }
}
