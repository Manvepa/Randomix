<?php

declare(strict_types=1);

namespace Randomix\Validators;

use InvalidArgumentException;

final class ExcelHeadersValidator
{
    /**
     * Recibe el esquema configurado para validar los encabezados.
     */
    public function __construct(
        private readonly array $requiredHeaders
    ) {
        // Comprueba que el esquema recibido tenga nombres válidos.
        if (
            $this->requiredHeaders === []
            || count($this->requiredHeaders) !== count(
                array_unique($this->requiredHeaders)
            )
        ) {
            throw new InvalidArgumentException(
                'El esquema debe contener encabezados únicos.'
            );
        }

        foreach ($this->requiredHeaders as $header) {
            if (!is_string($header) || trim($header) === '') {
                throw new InvalidArgumentException(
                    'Todos los encabezados deben ser textos no vacíos.'
                );
            }
        }
    }

    /**
     * Devuelve los encabezados requeridos por la configuración recibida.
     */
    public function getRequiredHeaders(): array
    {
        return $this->requiredHeaders;
    }

    /**
     * Comprueba encabezados faltantes, adicionales y duplicados.
     *
     * Permite cambiar el orden de las columnas, pero exige que los
     * nombres coincidan exactamente con el esquema configurado.
     */
    public function validate(array $headers): array
    {
        // Normaliza los valores y elimina espacios exteriores.
        $normalizedHeaders = array_map(
            static fn (mixed $header): string => trim(
                (string) ($header ?? '')
            ),
            $headers
        );

        // Omite celdas vacías para identificar faltantes correctamente.
        $nonEmptyHeaders = array_values(
            array_filter(
                $normalizedHeaders,
                static fn (string $header): bool => $header !== ''
            )
        );

        // Detecta encabezados que aparecen más de una vez.
        $headerCounts = array_count_values($nonEmptyHeaders);
        $duplicates = [];

        foreach ($headerCounts as $header => $count) {
            if ($count > 1) {
                $duplicates[] = $header;
            }
        }

        // Excluye la columna opcional de numeración del esquema oficial.
        // Se conserva en la validación de duplicados para detectar
        // archivos que tengan más de una columna con el encabezado #.
        $headersForSchema = array_values(
            array_filter(
                $nonEmptyHeaders,
                static fn (string $header): bool => $header !== '#'
            )
        );

        // Identifica encabezados obligatorios que no están presentes.
        $missing = array_values(
            array_diff(
                $this->requiredHeaders,
                array_unique($headersForSchema)
            )
        );

        // Identifica encabezados que no pertenecen al esquema.
        $extra = array_values(
            array_diff(
                array_unique($headersForSchema),
                $this->requiredHeaders
            )
        );

        // Solo acepta el archivo si cumple las tres condiciones.
        $valid = $missing === []
            && $extra === []
            && $duplicates === [];

        return [
            'valid' => $valid,
            'missing' => $missing,
            'extra' => $extra,
            'duplicates' => $duplicates,
        ];
    }
}
