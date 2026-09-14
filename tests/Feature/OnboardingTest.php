<?php

namespace Tests\Feature;

use App\Models\DocumentoUsuario;
use App\Models\SolicitudInversion;
use App\Models\Usuario;
use App\Services\NivelKyc;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Onboarding y bifurcación de KYC (manual §3.1.1).
 *
 * Aquí se fija la regla regulatoria: quién debe entregar documentación de
 * identidad y cuándo se cruza el umbral que obliga a escalar de nivel.
 * Relajarla por accidente es un incumplimiento de PLD, no un bug de interfaz.
 */
class OnboardingTest extends TestCase
{
    use DatabaseTransactions;

    private const FIRMA = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private function crearUsuario(array $atributos = []): Usuario
    {
        $usuario = new Usuario();

        $usuario->forceFill(array_merge([
            'email' => 'qa.onb.' . uniqid() . '@topkapital.test',
            'passwordHash' => Hash::make('Qx7!mvzp'),
            'nombre' => 'Fernanda',
            'estatus' => Usuario::ESTATUS_SIN_INFO,
            'rol' => Usuario::ROL_INVERSIONISTA,
            'intentosLogin' => 10,
            'tipoPersona' => 1,
            'tipoInversionista' => NivelKyc::TIPO_PRINCIPIANTE,
        ], $atributos));

        $usuario->save();

        return $usuario;
    }

    private function kyc(): NivelKyc
    {
        return app(NivelKyc::class);
    }

    private function datosValidos(array $sobrescribir = []): array
    {
        return array_merge([
            'nombre' => 'Fernanda',
            'apellidoPaterno' => 'Prueba',
            'apellidoMaterno' => 'Test',
            'RFC' => 'PETF900101AB1',
            'CURP' => 'PETF900101MDFRSR01',
            'telefono' => '5512345678',
            'fechaNacimiento' => '1990-01-01',
            'nacionalidad' => 'Mexicana',
            'profesion' => 'Arquitecta',
            'codigoPostal' => '06700',
            'calle' => 'Álvaro Obregón',
            'numeroExterior' => '100',
            'numeroInterior' => '',
            'colonia' => 'Roma Norte',
            'municipio_delegacion' => 'Cuauhtémoc',
            'genero' => 2,
            'tipoInversionista' => NivelKyc::TIPO_PRINCIPIANTE,
            'puestoGobierno' => 0,
        ], $sobrescribir);
    }

    /*
    |--------------------------------------------------------------------------
    | Nivel de KYC
    |--------------------------------------------------------------------------
    */

    public function test_una_persona_fisica_principiante_es_nivel_1(): void
    {
        $usuario = $this->crearUsuario();

        $this->assertSame(NivelKyc::NIVEL_SIMPLIFICADO, $this->kyc()->nivelDe($usuario));
        $this->assertFalse($this->kyc()->requiereDocumentacion($usuario));
    }

    /** El manual es explícito: todas las personas morales, sin importar el monto. */
    public function test_una_persona_moral_siempre_es_nivel_2(): void
    {
        $usuario = $this->crearUsuario(['tipoPersona' => 2]);

        $this->assertSame(NivelKyc::NIVEL_COMPLETO, $this->kyc()->nivelDe($usuario));
        $this->assertTrue($this->kyc()->requiereDocumentacion($usuario));
    }

    public function test_experto_y_relacionado_exigen_documentacion(): void
    {
        foreach ([NivelKyc::TIPO_EXPERTO, NivelKyc::TIPO_RELACIONADO] as $tipo) {
            $usuario = $this->crearUsuario(['tipoInversionista' => $tipo]);

            $this->assertTrue(
                $this->kyc()->requiereDocumentacion($usuario),
                "El tipo de inversionista {$tipo} debe exigir documentación.",
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Umbral de escalamiento
    |--------------------------------------------------------------------------
    */

    public function test_por_debajo_del_umbral_no_hay_que_escalar(): void
    {
        $usuario = $this->crearUsuario();

        $this->assertFalse($this->kyc()->debeEscalarPara($usuario, 4999.0));
    }

    public function test_al_superar_el_umbral_hay_que_escalar(): void
    {
        $usuario = $this->crearUsuario();

        $this->assertTrue($this->kyc()->debeEscalarPara($usuario, 5000.01));
    }

    /** Justo en el umbral NO se escala: la regla dice "supere los $5,000". */
    public function test_exactamente_en_el_umbral_no_se_escala(): void
    {
        $usuario = $this->crearUsuario();

        $this->assertFalse($this->kyc()->debeEscalarPara($usuario, 5000.0));
    }

    public function test_el_umbral_cuenta_lo_ya_invertido(): void
    {
        $usuario = $this->crearUsuario();

        $inversion = new SolicitudInversion();
        $inversion->forceFill([
            'usuarioId' => $usuario->getKey(),
            'proyectoId' => 1,
            'monto' => 4000,
            'confirmada' => 1,
            'devuelto' => 0,
            'cancelada' => 0,
            'estado' => 1,
            'createdAt' => now(),
        ]);
        $inversion->save();

        // 4,000 ya invertidos + 500 nuevos = 4,500: todavía cabe.
        $this->assertFalse($this->kyc()->debeEscalarPara($usuario, 500.0));

        // 4,000 + 1,500 = 5,500: se pasa.
        $this->assertTrue($this->kyc()->debeEscalarPara($usuario, 1500.0));
    }

    /**
     * Quien ya entregó identificación no vuelve a toparse con el umbral: el
     * manual dice que el Nivel 2 se mantiene de forma permanente.
     */
    public function test_con_documentacion_entregada_el_umbral_deja_de_aplicar(): void
    {
        $usuario = $this->crearUsuario();

        $doc = DocumentoUsuario::paraUsuario((int) $usuario->getKey());
        $doc->forceFill([
            'ineFrente' => 'ine-frente.jpg',
            'ineReverso' => 'ine-reverso.jpg',
            'fotoIne' => 'selfie.jpg',
        ])->save();

        $this->assertTrue($this->kyc()->tieneDocumentacionDeInversion($usuario->fresh()));
        $this->assertFalse($this->kyc()->debeEscalarPara($usuario->fresh(), 999999.0));
    }

    /** Las tres piezas, no dos: identificación por ambos lados y la foto. */
    public function test_la_documentacion_incompleta_no_cuenta(): void
    {
        $usuario = $this->crearUsuario();

        $doc = DocumentoUsuario::paraUsuario((int) $usuario->getKey());
        $doc->forceFill(['ineFrente' => 'a.jpg', 'ineReverso' => 'b.jpg'])->save();

        $this->assertFalse($this->kyc()->tieneDocumentacionDeInversion($usuario->fresh()));
        $this->assertTrue($this->kyc()->debeEscalarPara($usuario->fresh(), 6000.0));
    }

    /**
     * La persona moral queda fuera de esta comprobación a propósito: ya tuvo
     * que entregar documentación para cerrar su onboarding.
     */
    public function test_la_persona_moral_no_pasa_por_el_umbral(): void
    {
        $usuario = $this->crearUsuario(['tipoPersona' => 2]);

        $this->assertFalse($this->kyc()->debeEscalarPara($usuario, 999999.0));
    }

    /*
    |--------------------------------------------------------------------------
    | Wizard
    |--------------------------------------------------------------------------
    */

    public function test_el_wizard_exige_sesion(): void
    {
        $this->get('/onboarding')->assertRedirect(route('login'));
    }

    public function test_guarda_la_informacion_general_y_avanza_el_estatus(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)
            ->post('/onboarding/informacion', $this->datosValidos())
            ->assertSessionHasNoErrors();

        $fresco = $usuario->fresh();

        $this->assertSame('PETF900101AB1', $fresco->RFC);
        $this->assertSame(Usuario::ESTATUS_INFO_COMPLETA, (int) $fresco->estatus);
    }

    /** El estatus sólo avanza; reeditar el perfil no debe degradarlo. */
    public function test_reeditar_no_degrada_un_estatus_mas_alto(): void
    {
        $usuario = $this->crearUsuario(['estatus' => Usuario::ESTATUS_APROBADO_INVERSION]);

        $this->actingAs($usuario)->post('/onboarding/informacion', $this->datosValidos());

        $this->assertSame(
            Usuario::ESTATUS_APROBADO_INVERSION,
            (int) $usuario->fresh()->estatus,
        );
    }

    public function test_el_rfc_de_persona_moral_lleva_doce_caracteres(): void
    {
        $moral = $this->crearUsuario(['tipoPersona' => 2]);

        // El de 13 es de persona física: debe rechazarse.
        $this->actingAs($moral)
            ->post('/onboarding/informacion', $this->datosValidos(['RFC' => 'PETF900101AB1']))
            ->assertSessionHasErrors('RFC');

        $this->actingAs($moral)
            ->post('/onboarding/informacion', $this->datosValidos([
                'RFC' => 'TKD900101AB1',
                'CURP' => null,
                'apellidoPaterno' => null,
                'fechaNacimiento' => null,
                'genero' => null,
            ]))
            ->assertSessionHasNoErrors();
    }

    /*
    |--------------------------------------------------------------------------
    | Constancia y contrato
    |--------------------------------------------------------------------------
    */

    public function test_la_constancia_exige_reconocer_todos_los_riesgos(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)
            ->post('/onboarding/constancia', [
                'riesgoPerdida' => true,
                'riesgoLiquidez' => true,
                'riesgoInformacion' => true,
                'riesgoRendimiento' => true,
                'sinAprobacion' => true,
                // Falta sinAsesoria.
            ])
            ->assertSessionHasErrors('sinAsesoria');

        $this->assertNull(
            DocumentoUsuario::deUsuario((int) $usuario->getKey())?->constanciaFirmadoEn,
        );
    }

    public function test_firma_la_constancia_y_queda_la_evidencia(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)->post('/onboarding/constancia', [
            'riesgoPerdida' => true,
            'riesgoLiquidez' => true,
            'riesgoInformacion' => true,
            'riesgoRendimiento' => true,
            'sinAprobacion' => true,
            'sinAsesoria' => true,
        ])->assertSessionHasNoErrors();

        $doc = DocumentoUsuario::deUsuario((int) $usuario->getKey());

        $this->assertNotNull($doc->constanciaFirmadoEn);
        $this->assertNotNull($doc->ipFirma, 'La IP es parte de la evidencia de la firma.');
    }

    /** El contrato no puede firmarse antes que la constancia. */
    public function test_el_contrato_exige_la_constancia_previa(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)
            ->post('/onboarding/contrato', ['firma' => self::FIRMA])
            ->assertSessionHasErrors('firma');

        $this->assertNull(
            DocumentoUsuario::deUsuario((int) $usuario->getKey())?->contratoFirmadoEn,
        );
    }

    public function test_firma_el_contrato_con_firma_digitalizada(): void
    {
        $usuario = $this->crearUsuario();
        $this->firmarConstancia($usuario);

        $this->actingAs($usuario)
            ->post('/onboarding/contrato', ['firma' => self::FIRMA])
            ->assertSessionHasNoErrors();

        $doc = DocumentoUsuario::deUsuario((int) $usuario->getKey());

        $this->assertNotNull($doc->contratoFirmadoEn);
        $this->assertNotNull($doc->firmaBase64);
    }

    public function test_rechaza_una_firma_que_no_sea_imagen(): void
    {
        $usuario = $this->crearUsuario();
        $this->firmarConstancia($usuario);

        $this->actingAs($usuario)
            ->post('/onboarding/contrato', ['firma' => 'no-soy-una-imagen'])
            ->assertSessionHasErrors('firma');
    }

    /** Se firma una sola vez: la fecha original es la evidencia. */
    public function test_el_contrato_no_se_refirma(): void
    {
        $usuario = $this->crearUsuario();
        $this->firmarConstancia($usuario);

        $this->actingAs($usuario)->post('/onboarding/contrato', ['firma' => self::FIRMA]);
        $primera = DocumentoUsuario::deUsuario((int) $usuario->getKey())->contratoFirmadoEn;

        $this->travel(2)->days();

        $this->actingAs($usuario)->post('/onboarding/contrato', ['firma' => self::FIRMA]);
        $segunda = DocumentoUsuario::deUsuario((int) $usuario->getKey())->contratoFirmadoEn;

        $this->assertSame($primera->toDateTimeString(), $segunda->toDateTimeString());
    }

    /*
    |--------------------------------------------------------------------------
    | Envío a revisión
    |--------------------------------------------------------------------------
    */

    public function test_no_se_puede_enviar_a_revision_con_pasos_pendientes(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)
            ->post('/onboarding/enviar')
            ->assertSessionHasErrors('wizard');
    }

    public function test_un_nivel_1_completo_si_puede_enviarse(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)->post('/onboarding/informacion', $this->datosValidos());
        $this->firmarConstancia($usuario);
        $this->actingAs($usuario)->post('/onboarding/contrato', ['firma' => self::FIRMA]);

        $this->actingAs($usuario)
            ->post('/onboarding/enviar')
            ->assertSessionHasNoErrors();
    }

    /**
     * Un Nivel 2 con todo lo demás listo pero sin identificación NO puede
     * enviarse. Es la comprobación que evita que alguien que debe entregar
     * documentos se cuele a revisión sin ellos.
     */
    public function test_un_nivel_2_sin_identificacion_no_puede_enviarse(): void
    {
        $usuario = $this->crearUsuario(['tipoInversionista' => NivelKyc::TIPO_EXPERTO]);

        $this->actingAs($usuario)->post('/onboarding/informacion', $this->datosValidos([
            'tipoInversionista' => NivelKyc::TIPO_EXPERTO,
        ]));
        $this->firmarConstancia($usuario);
        $this->actingAs($usuario)->post('/onboarding/contrato', ['firma' => self::FIRMA]);

        $this->actingAs($usuario)
            ->post('/onboarding/enviar')
            ->assertSessionHasErrors('wizard');
    }

    private function firmarConstancia(Usuario $usuario): void
    {
        $this->actingAs($usuario)->post('/onboarding/constancia', [
            'riesgoPerdida' => true,
            'riesgoLiquidez' => true,
            'riesgoInformacion' => true,
            'riesgoRendimiento' => true,
            'sinAprobacion' => true,
            'sinAsesoria' => true,
        ]);
    }
}
