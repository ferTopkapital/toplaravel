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
 * Alta de clientes inversionistas (manual §3.1.1).
 */
class RegistroTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'Zr4$kwnb';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function datos(array $sobrescribir = []): array
    {
        return array_merge([
            'email' => 'qa.registro.' . uniqid() . '@topkapital.test',
            'nombre' => 'Fernanda',
            'apellidoPaterno' => 'Prueba',
            'apellidoMaterno' => 'Test',
            'tipoPersona' => 1,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terminos' => true,
        ], $sobrescribir);
    }

    public function test_crea_la_cuenta_sin_verificar_y_envia_el_codigo(): void
    {
        $datos = $this->datos();

        $this->post('/registro', $datos)->assertRedirect(route('registro.verificar'));

        $usuario = Usuario::porEmail($datos['email']);

        $this->assertNotNull($usuario);
        // Nace sin verificar; sólo pasa a 10 cuando confirma el correo.
        $this->assertSame(Usuario::ESTATUS_SIN_EMAIL, (int) $usuario->estatus);
        $this->assertSame(Usuario::ROL_INVERSIONISTA, (int) $usuario->rol);
        $this->assertTrue((bool) $usuario->terminosCondiciones);
        $this->assertTrue(Hash::check(self::PASSWORD, $usuario->passwordHash));

        Mail::assertSent(CodigoVerificacion::class, fn ($m) => $m->hasTo($datos['email']));
    }

    public function test_la_contrasena_inicial_queda_en_el_historial(): void
    {
        $datos = $this->datos();

        $this->post('/registro', $datos);

        $usuario = Usuario::porEmail($datos['email']);

        $this->assertTrue(
            HistorialContrasena::yaFueUsada((int) $usuario->getKey(), self::PASSWORD),
            'La contraseña del alta debe registrarse para impedir su reúso futuro.',
        );
    }

    public function test_verificar_el_codigo_activa_la_cuenta(): void
    {
        $datos = $this->datos();
        $this->post('/registro', $datos);

        $usuario = Usuario::porEmail($datos['email']);

        $this->post('/registro/verificar', ['codigo' => $usuario->fresh()->codigoLogin])
            ->assertRedirect(route('login'));

        $fresco = $usuario->fresh();

        $this->assertSame(Usuario::ESTATUS_SIN_INFO, (int) $fresco->estatus);
        $this->assertNull($fresco->codigoLogin, 'El código debe consumirse al usarse.');
    }

    public function test_un_codigo_incorrecto_no_activa_la_cuenta(): void
    {
        $datos = $this->datos();
        $this->post('/registro', $datos);

        $this->post('/registro/verificar', ['codigo' => 'XXXXXXXX'])
            ->assertSessionHasErrors('codigo');

        $this->assertSame(
            Usuario::ESTATUS_SIN_EMAIL,
            (int) Usuario::porEmail($datos['email'])->estatus,
        );
    }

    public function test_exige_aceptar_terminos_y_aviso_de_privacidad(): void
    {
        $datos = $this->datos(['terminos' => false]);

        $this->post('/registro', $datos)->assertSessionHasErrors('terminos');

        $this->assertNull(Usuario::porEmail($datos['email']));
    }

    public function test_la_contrasena_debe_cumplir_la_politica(): void
    {
        $datos = $this->datos(['password' => '123456', 'password_confirmation' => '123456']);

        $this->post('/registro', $datos)->assertSessionHasErrors('password');

        $this->assertNull(Usuario::porEmail($datos['email']));
    }

    /**
     * Un correo ya registrado y verificado no debe delatarse: responder "ya
     * existe" convertiría el formulario en un detector de clientes.
     */
    public function test_no_delata_que_un_correo_ya_esta_registrado(): void
    {
        $existente = new Usuario();
        $existente->forceFill([
            'email' => 'qa.existente.' . uniqid() . '@topkapital.test',
            'passwordHash' => Hash::make('Qx7!mvzp'),
            'nombre' => 'Ya',
            'estatus' => Usuario::ESTATUS_APROBADO_INVERSION,
            'rol' => Usuario::ROL_INVERSIONISTA,
            'intentosLogin' => 10,
        ]);
        $existente->save();

        $this->post('/registro', $this->datos(['email' => $existente->email]))
            ->assertRedirect(route('registro.verificar'))
            ->assertSessionHasNoErrors();

        // Ni se le cambió la contraseña ni se le mandó nada.
        $this->assertTrue(Hash::check('Qx7!mvzp', $existente->fresh()->passwordHash));
        Mail::assertNothingSent();
    }

    /**
     * En cambio, un registro que nunca se verificó sí se reutiliza: alguien
     * que no recibió el correo debe poder volver a intentarlo.
     */
    public function test_reutiliza_un_registro_que_nunca_se_verifico(): void
    {
        $pendiente = new Usuario();
        $pendiente->forceFill([
            'email' => 'qa.pendiente.' . uniqid() . '@topkapital.test',
            'passwordHash' => Hash::make('Qx7!mvzp'),
            'nombre' => 'Pendiente',
            'estatus' => Usuario::ESTATUS_SIN_EMAIL,
            'rol' => Usuario::ROL_INVERSIONISTA,
            'intentosLogin' => 10,
        ]);
        $pendiente->save();

        $this->post('/registro', $this->datos(['email' => $pendiente->email]))
            ->assertRedirect(route('registro.verificar'));

        // Se actualizó con los datos nuevos y volvió a salir el código.
        $this->assertTrue(Hash::check(self::PASSWORD, $pendiente->fresh()->passwordHash));
        Mail::assertSent(CodigoVerificacion::class);

        // Y no se creó una cuenta duplicada.
        $this->assertSame(
            1,
            Usuario::query()->where('email', $pendiente->email)->count(),
        );
    }
}
