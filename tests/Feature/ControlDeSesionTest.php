<?php

namespace Tests\Feature;

use App\Models\SesionWeb;
use App\Models\Usuario;
use App\Support\SesionUnica;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cierre por inactividad, sesión única y bitácora regulatoria
 * (manual §4.4.4, y OFICO para `sesiones_web`).
 */
class ControlDeSesionTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Qx7!mvzp';

    protected function setUp(): void
    {
        parent::setUp();

        // El registro de sesión única vive en caché; se parte de cero.
        Cache::clear();
    }

    private function crearUsuario(array $atributos = []): Usuario
    {
        $usuario = new Usuario();

        $usuario->forceFill(array_merge([
            'email' => 'qa.sesion.' . uniqid() . '@topkapital.test',
            'passwordHash' => Hash::make(self::PASSWORD),
            'nombre' => 'Fernanda',
            'estatus' => Usuario::ESTATUS_APROBADO_INVERSION,
            'rol' => Usuario::ROL_INVERSIONISTA,
            'intentosLogin' => 10,
            'imagenPerfil' => 2,
            'ultimoLogin' => now()->subDays(2),
        ], $atributos));

        $usuario->save();

        return $usuario;
    }

    private function entrar(Usuario $usuario): void
    {
        $this->post('/login/identificar', ['email' => $usuario->email]);
        $this->post('/login', ['password' => self::PASSWORD]);
    }

    public function test_el_ingreso_queda_en_la_bitacora_regulatoria(): void
    {
        $usuario = $this->crearUsuario();

        $this->entrar($usuario);

        $this->assertTrue(
            SesionWeb::query()
                ->where('usuarioId', $usuario->getKey())
                ->whereDate('fechaSesion', now()->toDateString())
                ->exists(),
            'El acceso debe quedar en sesiones_web: OFICO lo usa para contar accesos.',
        );
    }

    public function test_la_bitacora_no_duplica_accesos_del_mismo_dia(): void
    {
        $usuario = $this->crearUsuario();

        // Dos ingresos el mismo día cuentan como UN acceso en el reporte.
        $this->entrar($usuario);
        $this->post('/logout');
        $this->entrar($usuario);

        $this->assertSame(
            1,
            SesionWeb::query()
                ->where('usuarioId', $usuario->getKey())
                ->whereDate('fechaSesion', now()->toDateString())
                ->count(),
        );
    }

    /** Minutos de inactividad configurados para el entorno de pruebas. */
    private function limiteMinutos(): int
    {
        return (int) config('topkapital.sesion.minutos_inactividad');
    }

    public function test_la_sesion_se_cierra_tras_el_periodo_de_inactividad(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrar($usuario);
        $this->assertAuthenticated();

        // Se avanza el reloj en vez de manipular la sesión: así se ejercita
        // el mismo camino que en producción.
        $this->travel($this->limiteMinutos() + 1)->minutes();

        $this->get('/')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_la_actividad_real_mantiene_viva_la_sesion(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrar($usuario);

        // Navega justo antes de expirar: eso renueva el reloj...
        $this->travel($this->limiteMinutos() - 1)->minutes();
        $this->get('/')->assertOk();

        // ...así que un rato después sigue dentro, aunque ya pasó más tiempo
        // del límite contado desde el ingreso.
        $this->travel($this->limiteMinutos() - 1)->minutes();
        $this->get('/')->assertOk();

        $this->assertAuthenticated();
    }

    public function test_consultar_el_estado_no_mantiene_viva_la_sesion(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrar($usuario);

        // Sondea el estado repetidamente mientras corre el tiempo. Si el
        // sondeo renovara el reloj, una pestaña abierta viviría para siempre.
        $ultima = null;

        for ($i = 0; $i < $this->limiteMinutos() + 1; $i++) {
            $this->travel(1)->minutes();
            $ultima = $this->getJson('/sesion/estado');
        }

        // El último sondeo, ya pasado el límite, debe encontrarse la sesión
        // cerrada. (Se afirma sobre esta respuesta y no sobre `/`, que es una
        // ruta pública y respondería 200 aunque la sesión ya no exista.)
        $ultima->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_renovar_si_mantiene_viva_la_sesion(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrar($usuario);

        $this->travel($this->limiteMinutos() - 1)->minutes();

        // El usuario dijo explícitamente "no cerrar sesión".
        $this->postJson('/sesion/renovar')->assertOk();

        // Pasado el límite original, la sesión sigue viva gracias a esa acción.
        $this->travel($this->limiteMinutos() - 1)->minutes();

        $this->get('/')->assertOk();

        $this->assertAuthenticated();
    }

    public function test_una_sesion_desplazada_por_otra_queda_fuera(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrar($usuario);
        $this->assertAuthenticated();

        // Simula que el mismo identificador de cliente entró en otro
        // dispositivo: el registro pasa a apuntar a OTRO token.
        Cache::put('sesion_activa:' . $usuario->getKey(), 'token-de-otro-dispositivo', now()->addHour());

        $this->get('/')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_sin_registro_previo_la_sesion_no_se_cierra(): void
    {
        $usuario = $this->crearUsuario();
        $this->entrar($usuario);

        // Entrada de caché caducada o sesión anterior a este control: cerrar
        // por eso sería un falso positivo.
        app(SesionUnica::class)->olvidar($usuario);

        $this->get('/')->assertOk();

        $this->assertAuthenticated();
    }
}
