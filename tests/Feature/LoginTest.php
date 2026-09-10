<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\CodigoOtp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Flujo de inicio de sesión (manual §1.4, §4.3.1 y §4.4).
 *
 * Usa DatabaseTransactions: el usuario de prueba se inserta y se revierte al
 * terminar, así que no queda nada en la base compartida.
 */
class LoginTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Qx7!mvzp';

    private function crearUsuario(array $atributos = []): Usuario
    {
        $usuario = new Usuario();

        $usuario->forceFill(array_merge([
            'email' => 'qa.login.' . uniqid() . '@topkapital.test',
            'passwordHash' => Hash::make(self::PASSWORD),
            'nombre' => 'Fernanda',
            'apellidoPaterno' => 'Prueba',
            'estatus' => Usuario::ESTATUS_APROBADO_INVERSION,
            'rol' => Usuario::ROL_INVERSIONISTA,
            'intentosLogin' => 10,
            'imagenPerfil' => 3,
            'ultimoLogin' => now()->subDays(2),
        ], $atributos));

        $usuario->save();

        return $usuario;
    }

    public function test_el_primer_paso_muestra_la_imagen_de_seguridad(): void
    {
        $usuario = $this->crearUsuario();

        $this->post('/login/identificar', ['email' => $usuario->email])
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('paso', 'credenciales')
                ->where('imagenSeguridad', '/images/seguridad/img-03.jpg')
                ->where('requiereOtp', false)
            );
    }

    public function test_no_pide_contrasena_si_el_correo_no_existe(): void
    {
        $this->post('/login/identificar', ['email' => 'nadie@topkapital.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_entra_con_credenciales_correctas(): void
    {
        $usuario = $this->crearUsuario();

        $this->post('/login/identificar', ['email' => $usuario->email]);

        $this->post('/login', ['password' => self::PASSWORD])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($usuario->fresh(), 'web');
    }

    public function test_una_contrasena_incorrecta_descuenta_un_intento(): void
    {
        $usuario = $this->crearUsuario(['intentosLogin' => 10]);

        $this->post('/login/identificar', ['email' => $usuario->email]);

        $this->post('/login', ['password' => 'Incorrecta1!'])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertSame(9, $usuario->fresh()->intentosLogin);
    }

    public function test_al_agotar_los_intentos_se_exige_segundo_factor(): void
    {
        $usuario = $this->crearUsuario(['intentosLogin' => 0]);

        $this->post('/login/identificar', ['email' => $usuario->email])
            ->assertInertia(fn ($page) => $page->where('requiereOtp', true));

        // El código ya quedó emitido en la base.
        $this->assertNotNull($usuario->fresh()->codigoLogin);
    }

    /**
     * Es el bug DDS-897 de la app Yii2: con el contador agotado, una
     * contraseña correcta NO debe bastar para entrar.
     */
    public function test_con_intentos_agotados_la_contrasena_correcta_no_basta(): void
    {
        $usuario = $this->crearUsuario(['intentosLogin' => 0]);

        $this->post('/login/identificar', ['email' => $usuario->email]);

        $this->post('/login', ['password' => self::PASSWORD])
            ->assertSessionHasErrors('codigo');

        $this->assertGuest();
    }

    public function test_entra_con_el_codigo_correcto_y_el_codigo_se_consume(): void
    {
        $usuario = $this->crearUsuario(['intentosLogin' => 0]);

        $this->post('/login/identificar', ['email' => $usuario->email]);

        $codigo = $usuario->fresh()->codigoLogin;

        $this->post('/login', ['password' => self::PASSWORD, 'codigo' => $codigo])
            ->assertRedirect('/');

        $this->assertAuthenticated('web');

        // Un código de un solo uso no puede seguir vivo tras usarse.
        $this->assertNull($usuario->fresh()->codigoLogin);
    }

    public function test_un_codigo_expirado_no_sirve(): void
    {
        $usuario = $this->crearUsuario(['intentosLogin' => 0]);

        $this->post('/login/identificar', ['email' => $usuario->email]);

        // Se envejece el código más allá de su vigencia de 2 minutos.
        $usuario->forceFill([
            'fechaCodigoLogin' => now()->subSeconds(config('topkapital.otp.vigencia_segundos') + 5),
        ])->save();

        $this->post('/login', [
            'password' => self::PASSWORD,
            'codigo' => $usuario->fresh()->codigoLogin,
        ])->assertSessionHasErrors('codigo');

        $this->assertGuest();
    }

    public function test_al_entrar_se_conserva_la_fecha_del_ingreso_anterior(): void
    {
        $anterior = now()->subDays(5)->startOfSecond();
        $usuario = $this->crearUsuario(['ultimoLogin' => $anterior]);

        $this->post('/login/identificar', ['email' => $usuario->email]);
        $this->post('/login', ['password' => self::PASSWORD]);

        $fresco = $usuario->fresh();

        // El manual §1.4 obliga a mostrar el ingreso PREVIO, no el actual.
        $this->assertSame(
            $anterior->toDateTimeString(),
            $fresco->ultimoLoginAnterior->toDateTimeString(),
        );
        $this->assertTrue($fresco->ultimoLogin->isToday());
    }

    public function test_al_entrar_se_reinicia_el_contador_de_intentos(): void
    {
        $usuario = $this->crearUsuario(['intentosLogin' => 4]);

        $this->post('/login/identificar', ['email' => $usuario->email]);
        $this->post('/login', ['password' => self::PASSWORD]);

        $this->assertSame(10, $usuario->fresh()->intentosLogin);
    }

    public function test_una_cuenta_bloqueada_por_pld_no_puede_identificarse(): void
    {
        $usuario = $this->crearUsuario(['estatus' => Usuario::ESTATUS_BLOQUEADO_PLD]);

        $this->post('/login/identificar', ['email' => $usuario->email])
            ->assertSessionHasErrors('email');
    }

    public function test_el_servicio_de_otp_emite_codigos_de_la_longitud_declarada(): void
    {
        $usuario = $this->crearUsuario();
        $otp = app(CodigoOtp::class);

        $codigo = $otp->generar($usuario);

        $this->assertSame(config('topkapital.otp.longitud'), strlen($codigo));
        $this->assertTrue($otp->validar($usuario->fresh(), $codigo));
        $this->assertFalse($otp->validar($usuario->fresh(), 'XXXXXXXX'));
    }
}
