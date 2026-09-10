<?php

namespace Tests\Feature;

use App\Models\Campana;
use App\Models\Proyecto;
use App\Models\SolicitudInversion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Portal del inversionista (manual §1.1.2).
 *
 * Lo que se prueba aquí no es la maquetación sino las CIFRAS: qué cuenta como
 * capital vigente del cliente. Ese criterio sale del scope `vivas()` y de él
 * depende lo que el inversionista ve como su dinero.
 */
class PortalTest extends TestCase
{
    use DatabaseTransactions;

    private function crearInversionista(): Usuario
    {
        $usuario = new Usuario();

        $usuario->forceFill([
            'email' => 'qa.portal.' . uniqid() . '@topkapital.test',
            'passwordHash' => Hash::make('Qx7!mvzp'),
            'nombre' => 'Fernanda',
            'estatus' => Usuario::ESTATUS_APROBADO_INVERSION,
            'rol' => Usuario::ROL_INVERSIONISTA,
            'intentosLogin' => 10,
        ]);

        $usuario->save();

        return $usuario;
    }

    private function crearInversion(Usuario $u, float $monto, array $estado = []): SolicitudInversion
    {
        $inversion = new SolicitudInversion();

        $inversion->forceFill(array_merge([
            'usuarioId' => $u->getKey(),
            // Se apoya en el proyecto 1, que existe en el respaldo local.
            'proyectoId' => 1,
            'monto' => $monto,
            'confirmada' => 1,
            'devuelto' => 0,
            'cancelada' => 0,
            'estado' => 1,
            'createdAt' => now(),
        ], $estado));

        $inversion->save();

        return $inversion;
    }

    public function test_el_panel_exige_haber_iniciado_sesion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/proyectos')->assertRedirect(route('login'));
    }

    public function test_el_capital_invertido_suma_solo_las_inversiones_vigentes(): void
    {
        $usuario = $this->crearInversionista();

        $this->crearInversion($usuario, 10000);                          // cuenta
        $this->crearInversion($usuario, 5000);                           // cuenta
        $this->crearInversion($usuario, 7000, ['devuelto' => 1]);        // devuelta
        $this->crearInversion($usuario, 3000, ['confirmada' => 0]);      // sin confirmar
        $this->crearInversion($usuario, 9000, ['cancelada' => 1]);       // cancelada

        $this->actingAs($usuario)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Index')
                ->where('resumen.capitalInvertido', 15000)
                ->where('resumen.pendientes', 1)
            );
    }

    public function test_el_panel_no_mezcla_el_dinero_de_otro_cliente(): void
    {
        $mio = $this->crearInversionista();
        $ajeno = $this->crearInversionista();

        $this->crearInversion($mio, 1000);
        $this->crearInversion($ajeno, 50000);

        $this->actingAs($mio)
            ->get('/')
            ->assertInertia(fn ($page) => $page->where('resumen.capitalInvertido', 1000));
    }

    public function test_el_listado_de_proyectos_responde_por_etapa(): void
    {
        $usuario = $this->crearInversionista();

        $this->actingAs($usuario)
            ->get('/proyectos?etapa=' . Proyecto::ETAPA_FONDEADO)
            ->assertInertia(fn ($page) => $page
                ->component('Proyectos/Index')
                ->where('etapaActual', Proyecto::ETAPA_FONDEADO)
                ->has('etapas', count(Proyecto::ETAPAS))
            );
    }

    /** Una etapa inválida en la query string no debe reventar la pantalla. */
    public function test_una_etapa_invalida_cae_a_en_fondeo(): void
    {
        $usuario = $this->crearInversionista();

        $this->actingAs($usuario)
            ->get('/proyectos?etapa=999')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('etapaActual', Proyecto::ETAPA_EN_FONDEO));
    }

    public function test_el_detalle_muestra_lo_que_el_cliente_lleva_invertido(): void
    {
        $usuario = $this->crearInversionista();
        $this->crearInversion($usuario, 25000);

        $this->actingAs($usuario)
            ->get('/proyectos/1')
            ->assertInertia(fn ($page) => $page
                ->component('Proyectos/Detalle')
                ->where('miInversion', 25000)
                ->where('proyecto.id', 1)
            );
    }

    public function test_un_proyecto_no_publico_no_es_visible(): void
    {
        $usuario = $this->crearInversionista();

        // El proyecto 2 del respaldo local tiene visible = 0.
        $this->actingAs($usuario)->get('/proyectos/2')->assertNotFound();
    }

    /**
     * El avance de fondeo no puede pasar de 100 aunque se sobrefondee: la
     * barra de progreso se saldría de su caja.
     */
    public function test_el_avance_de_fondeo_se_acota_a_cien(): void
    {
        $campana = Campana::query()->first();

        if ($campana === null) {
            $this->markTestSkipped('El respaldo local no tiene campañas.');
        }

        $this->assertLessThanOrEqual(100.0, $campana->avance());
        $this->assertGreaterThanOrEqual(0.0, $campana->avance());
    }
}
