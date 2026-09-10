<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Traits que borran o recrean la base de datos.
     *
     * Este proyecto corre las pruebas contra la MISMA base que la aplicación
     * —una copia del respaldo de producción, compartida con la app Yii2—
     * porque el usuario de base de datos no tiene privilegios para crear una
     * base de pruebas aparte. Usar cualquiera de estos traits borraría las 44
     * tablas del negocio.
     *
     * Las pruebas que necesiten escribir deben usar `DatabaseTransactions`,
     * que revierte todo al terminar.
     *
     * @var list<class-string>
     */
    private const TRAITS_DESTRUCTIVOS = [
        RefreshDatabase::class,
        DatabaseMigrations::class,
        DatabaseTruncation::class,
    ];

    protected function setUp(): void
    {
        $this->abortarSiElTestBorraLaBase();

        parent::setUp();
    }

    /**
     * Corta la ejecución ANTES de que el trait pueda correr sus migraciones.
     *
     * Es una red de seguridad deliberada: el comentario en phpunit.xml explica
     * la regla, pero un comentario no detiene a nadie a las 11 de la noche.
     */
    private function abortarSiElTestBorraLaBase(): void
    {
        $usados = class_uses_recursive(static::class);

        foreach (self::TRAITS_DESTRUCTIVOS as $trait) {
            if (! in_array($trait, $usados, true)) {
                continue;
            }

            $corto = class_basename($trait);

            throw new RuntimeException(
                static::class . " usa {$corto}, que recrea la base de datos.\n\n"
                . "Las pruebas corren contra la base compartida con la app Yii2 "
                . "(copia del respaldo de producción): {$corto} borraría las 44 tablas "
                . "del negocio.\n\n"
                . 'Usa Illuminate\Foundation\Testing\DatabaseTransactions, que revierte '
                . "los cambios al terminar.\n"
                . 'Ver docs/PLAN-MIGRACION.md, sección 5.'
            );
        }
    }
}
