<?php

namespace Tests\Unit;

use App\Rules\PoliticaPassword;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La política de contraseñas está declarada ante la CNBV (manual §4.1), así
 * que se prueba caso por caso: si alguien la relaja sin querer, esto lo caza.
 */
class PoliticaPasswordTest extends TestCase
{
    private function falla(string $password, ?string $email = null): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => [new PoliticaPassword($email)]],
        )->fails();
    }

    #[DataProvider('contrasenasValidas')]
    public function test_acepta_contrasenas_que_cumplen(string $password): void
    {
        $this->assertFalse($this->falla($password), "Debió aceptar: {$password}");
    }

    public static function contrasenasValidas(): array
    {
        return [
            'mínima válida' => ['Ab1!xkqm'],
            'con símbolos variados' => ['Rmk@2026$Segvro'],
            'repetidos en el límite' => ['Xaaa1!qm'],       // 3 idénticos: permitido
            'secuencia en el límite' => ['Xabc1!qm'],       // 3 en secuencia: permitido
            'acentos' => ['Contraseñ1!xk'],
            'treinta caracteres' => ['Ab1!' . str_repeat('xq', 13)],
        ];
    }

    #[DataProvider('contrasenasInvalidas')]
    public function test_rechaza_contrasenas_que_no_cumplen(string $password): void
    {
        $this->assertTrue($this->falla($password), "Debió rechazar: {$password}");
    }

    public static function contrasenasInvalidas(): array
    {
        return [
            'muy corta' => ['Ab1!efg'],
            'muy larga' => ['Ab1!' . str_repeat('xq', 14)],
            'sin mayúscula' => ['ab1!efgh'],
            'sin minúscula' => ['AB1!EFGH'],
            'sin dígito' => ['Abcd!efgh'],
            'sin especial' => ['Abcd1efgh'],
            'cuatro idénticos' => ['Xaaaa1!q'],
            'cuatro idénticos mezclando mayúscula' => ['XAaaa1!q'],
            'cuatro en secuencia' => ['Xabcd1!q'],
            'cuatro letras seguidas al final' => ['Ab1!efgh'],
            'cuatro dígitos en secuencia' => ['Ax!1234yz'],
            'secuencia descendente' => ['Ax!4321yz'],
            'letras descendentes' => ['Ax!dcbayz'],
            'nombre de la institución' => ['Topkapital1!'],
            'institución con espacio' => ['Top Kapital1!x'],
        ];
    }

    public function test_rechaza_si_contiene_el_correo_del_usuario(): void
    {
        $this->assertTrue(
            $this->falla('Fernando.salas1!', 'fernando.salas@topkapital.com'),
            'Debió rechazar una contraseña que contiene la parte local del correo.',
        );
    }

    public function test_acepta_una_contrasena_ajena_al_correo(): void
    {
        $this->assertFalse(
            $this->falla('Qx7!mvzp', 'fernando.salas@topkapital.com'),
        );
    }

    public function test_no_confunde_el_salto_de_letra_a_digito_con_secuencia(): void
    {
        // 'x','y','z' es una corrida de 3 (permitida) y luego '1' rompe el tipo.
        $this->assertFalse($this->falla('Qxyz1!mv'));
    }
}
