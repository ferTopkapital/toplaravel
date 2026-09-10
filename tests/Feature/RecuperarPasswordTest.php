<?php

namespace Tests\Feature;

use App\Mail\CodigoVerificacion;
use App\Models\HistorialContrasena;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Restablecimiento y desbloqueo de contraseña (manual §4.2.4, §4.3.1, §4.4.2).
 */
class RecuperarPasswordTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD_VIEJA = 'Qx7!mvzp';

    private const PASSWORD_NUEVA = 'Zr4$kwnb';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function crearUsuario(array $atributos = []): Usuario
    {
        $usuario = new Usuario();

        $usuario->forceFill(array_merge([
            'email' => 'qa.reset.' . uniqid() . '@topkapital.test',
            'passwordHash' => Hash::make(self::PASSWORD_VIEJA),
            'nombre' => 'Fernanda',
            'estatus' => Usuario::ESTATUS_APROBADO_INVERSION,
            'rol' => Usuario::ROL_INVERSIONISTA,
            'intentosLogin' => 10,
            'imagenPerfil' => 1,
        ], $atributos));

        $usuario->save();

        return $usuario;
    }

    /** Recorre los pasos 1 y 2 y devuelve el código emitido. */
    private function pedirYVerificarCodigo(Usuario $usuario): string
    {
        $this->post('/recuperar', ['email' => $usuario->email]);

        $codigo = $usuario->fresh()->codigoRecuperarCuenta;

        $this->post('/recuperar/verificar', ['codigo' => $codigo]);

        return $codigo;
    }

    public function test_envia_el_codigo_a_una_cuenta_existente(): void
    {
        $usuario = $this->crearUsuario();

        $this->post('/recuperar', ['email' => $usuario->email])
            ->assertInertia(fn ($page) => $page->where('paso', 'verificar'));

        $this->assertNotNull($usuario->fresh()->codigoRecuperarCuenta);

        Mail::assertSent(CodigoVerificacion::class, fn ($mail) => $mail->hasTo($usuario->email));
    }

    /**
     * A diferencia del login —donde la imagen de seguridad obliga a
     * distinguir— aquí no se revela si el correo existe.
     */
    public function test_no_revela_si_el_correo_existe(): void
    {
        $this->post('/recuperar', ['email' => 'nadie@topkapital.test'])
            ->assertInertia(fn ($page) => $page->where('paso', 'verificar'))
            ->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_restablece_la_contrasena_con_el_codigo_correcto(): void
    {
        $usuario = $this->crearUsuario();

        $this->pedirYVerificarCodigo($usuario);

        $this->post('/recuperar/restablecer', [
            'password' => self::PASSWORD_NUEVA,
            'password_confirmation' => self::PASSWORD_NUEVA,
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(self::PASSWORD_NUEVA, $usuario->fresh()->passwordHash));
    }

    public function test_un_codigo_incorrecto_no_avanza(): void
    {
        $usuario = $this->crearUsuario();

        $this->post('/recuperar', ['email' => $usuario->email]);

        $this->post('/recuperar/verificar', ['codigo' => 'XXXXXXXX'])
            ->assertSessionHasErrors('codigo');
    }

    public function test_no_se_puede_restablecer_sin_haber_verificado_el_codigo(): void
    {
        $usuario = $this->crearUsuario();

        // Sólo el paso 1: nunca se verificó el código.
        $this->post('/recuperar', ['email' => $usuario->email]);

        $this->post('/recuperar/restablecer', [
            'password' => self::PASSWORD_NUEVA,
            'password_confirmation' => self::PASSWORD_NUEVA,
        ])->assertRedirect(route('password.solicitar'));

        $this->assertTrue(Hash::check(self::PASSWORD_VIEJA, $usuario->fresh()->passwordHash));
    }

    public function test_la_nueva_contrasena_debe_cumplir_la_politica(): void
    {
        $usuario = $this->crearUsuario();

        $this->pedirYVerificarCodigo($usuario);

        $this->post('/recuperar/restablecer', [
            'password' => '123456',
            'password_confirmation' => '123456',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::PASSWORD_VIEJA, $usuario->fresh()->passwordHash));
    }

    public function test_no_permite_reutilizar_una_contrasena_anterior(): void
    {
        $usuario = $this->crearUsuario();

        // El historial ya tiene la contraseña que se intentará reutilizar.
        HistorialContrasena::registrar(
            (int) $usuario->getKey(),
            Hash::make(self::PASSWORD_NUEVA),
        );

        $this->pedirYVerificarCodigo($usuario);

        $this->post('/recuperar/restablecer', [
            'password' => self::PASSWORD_NUEVA,
            'password_confirmation' => self::PASSWORD_NUEVA,
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::PASSWORD_VIEJA, $usuario->fresh()->passwordHash));
    }

    public function test_la_nueva_contrasena_queda_en_el_historial(): void
    {
        $usuario = $this->crearUsuario();

        $this->pedirYVerificarCodigo($usuario);

        $this->post('/recuperar/restablecer', [
            'password' => self::PASSWORD_NUEVA,
            'password_confirmation' => self::PASSWORD_NUEVA,
        ]);

        $this->assertTrue(
            HistorialContrasena::yaFueUsada((int) $usuario->getKey(), self::PASSWORD_NUEVA),
            'La contraseña recién puesta debe quedar registrada para impedir su reúso futuro.',
        );
    }

    /** Restablecer es también el desbloqueo del §4.3.1. */
    public function test_restablecer_desbloquea_una_cuenta_sin_intentos(): void
    {
        $usuario = $this->crearUsuario(['intentosLogin' => 0]);

        $this->pedirYVerificarCodigo($usuario);

        $this->post('/recuperar/restablecer', [
            'password' => self::PASSWORD_NUEVA,
            'password_confirmation' => self::PASSWORD_NUEVA,
        ]);

        $this->assertSame(10, $usuario->fresh()->intentosLogin);
    }

    public function test_el_codigo_se_consume_al_terminar(): void
    {
        $usuario = $this->crearUsuario();

        $this->pedirYVerificarCodigo($usuario);

        $this->post('/recuperar/restablecer', [
            'password' => self::PASSWORD_NUEVA,
            'password_confirmation' => self::PASSWORD_NUEVA,
        ]);

        $this->assertNull($usuario->fresh()->codigoRecuperarCuenta);
    }

    public function test_un_codigo_expirado_no_permite_restablecer(): void
    {
        $usuario = $this->crearUsuario();

        $this->pedirYVerificarCodigo($usuario);

        // Entre verificar y guardar se agotó la vigencia de 2 minutos.
        $this->travel(config('topkapital.otp.vigencia_segundos') + 10)->seconds();

        $this->post('/recuperar/restablecer', [
            'password' => self::PASSWORD_NUEVA,
            'password_confirmation' => self::PASSWORD_NUEVA,
        ])->assertRedirect(route('password.solicitar'));

        $this->assertTrue(Hash::check(self::PASSWORD_VIEJA, $usuario->fresh()->passwordHash));
    }
}
