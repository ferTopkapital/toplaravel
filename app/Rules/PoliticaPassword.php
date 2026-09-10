<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Política de contraseñas del Manual de Uso de Medios Electrónicos, §4.1.
 *
 * No es una preferencia de producto: está declarada ante la CNBV. Cualquier
 * cambio aquí es un cambio regulatorio.
 *
 * Exige:
 *   - Longitud entre 8 y 30 caracteres.
 *   - Al menos una mayúscula, una minúscula, un dígito y un carácter especial.
 *
 * Y prohíbe:
 *   - Que contenga el identificador de cliente (su correo o la parte local).
 *   - Que contenga el nombre de la institución.
 *   - Más de tres caracteres idénticos consecutivos ("aaaa").
 *   - Más de tres caracteres numéricos o alfabéticos en secuencia ("1234",
 *     "abcd", y también en reversa: "4321", "dcba").
 */
class PoliticaPassword implements ValidationRule
{
    public function __construct(
        /** Identificador de cliente, para verificar que no forme parte de la contraseña. */
        private ?string $email = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('La contraseña no es válida.');

            return;
        }

        $config = config('topkapital.password');

        $largo = mb_strlen($value);

        if ($largo < $config['min'] || $largo > $config['max']) {
            $fail("La contraseña debe tener entre {$config['min']} y {$config['max']} caracteres.");

            return;
        }

        if (! preg_match('/\p{Lu}/u', $value)) {
            $fail('La contraseña debe incluir al menos una letra mayúscula.');
        }

        if (! preg_match('/\p{Ll}/u', $value)) {
            $fail('La contraseña debe incluir al menos una letra minúscula.');
        }

        if (! preg_match('/\d/', $value)) {
            $fail('La contraseña debe incluir al menos un dígito.');
        }

        // "Especial" = cualquier cosa que no sea letra ni dígito. Se define por
        // exclusión a propósito: una lista blanca de símbolos dejaría fuera
        // caracteres perfectamente válidos y sólo estorbaría al usuario.
        if (! preg_match('/[^\p{L}\d]/u', $value)) {
            $fail('La contraseña debe incluir al menos un carácter especial.');
        }

        $minusculas = mb_strtolower($value);

        foreach ($this->terminosProhibidos() as $termino) {
            if ($termino !== '' && str_contains($minusculas, $termino)) {
                $fail('La contraseña no puede contener tu correo ni el nombre de la institución.');
                break;
            }
        }

        if ($this->tieneRepetidos($value, $config['max_repetidos'])) {
            $fail(
                "La contraseña no puede tener más de {$config['max_repetidos']} caracteres idénticos seguidos."
            );
        }

        if ($this->tieneSecuencia($minusculas, $config['max_secuenciales'])) {
            $fail(
                "La contraseña no puede tener más de {$config['max_secuenciales']} caracteres en secuencia."
            );
        }
    }

    /**
     * @return list<string>
     */
    private function terminosProhibidos(): array
    {
        $terminos = config('topkapital.password.prohibidas');

        if ($this->email !== null && $this->email !== '') {
            $email = mb_strtolower(trim($this->email));
            $terminos[] = $email;

            // También la parte local: si el correo es juan.perez@x.com, que la
            // contraseña no sea "Juan.perez1!".
            $local = strstr($email, '@', true);

            if (is_string($local) && mb_strlen($local) >= 3) {
                $terminos[] = $local;
            }
        }

        return array_values(array_filter($terminos));
    }

    /**
     * ¿Hay una corrida de más de $max caracteres idénticos?
     *
     * Se compara SIN distinguir mayúsculas: "Aaaa" es tan débil como "aaaa" y
     * el manual busca justamente evitar ese patrón. Es una lectura más estricta
     * que la literal de "idénticos", y en una política de contraseñas el lado
     * estricto es el correcto. Sólo afecta a contraseñas nuevas.
     */
    private function tieneRepetidos(string $valor, int $max): bool
    {
        return (bool) preg_match('/(.)\1{' . $max . ',}/iu', $valor);
    }

    /**
     * ¿Hay más de $max caracteres consecutivos en secuencia?
     *
     * Cuenta tanto ascendente como descendente, y sólo dentro del mismo tipo:
     * "ab12" no es una secuencia aunque los códigos sean contiguos, porque el
     * salto de letra a dígito rompe la corrida.
     */
    private function tieneSecuencia(string $valor, int $max): bool
    {
        $caracteres = mb_str_split($valor);
        $total = count($caracteres);

        if ($total <= $max) {
            return false;
        }

        $corridaAsc = 1;
        $corridaDesc = 1;

        for ($i = 1; $i < $total; $i++) {
            $anterior = $caracteres[$i - 1];
            $actual = $caracteres[$i];

            $mismoTipo = (ctype_digit($anterior) && ctype_digit($actual))
                || (ctype_alpha($anterior) && ctype_alpha($actual));

            if (! $mismoTipo) {
                $corridaAsc = $corridaDesc = 1;

                continue;
            }

            $delta = ord($actual) - ord($anterior);

            $corridaAsc = $delta === 1 ? $corridaAsc + 1 : 1;
            $corridaDesc = $delta === -1 ? $corridaDesc + 1 : 1;

            if ($corridaAsc > $max || $corridaDesc > $max) {
                return true;
            }
        }

        return false;
    }
}
